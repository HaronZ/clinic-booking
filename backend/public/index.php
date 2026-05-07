<?php
declare(strict_types=1);

/**
 * Front controller — every /api/* request lands here.
 *
 *   php -S localhost:8080 -t public
 *
 * Routes are registered against $router. Each handler must call Response::success()
 * or Response::error() — both exit. Exceptions are mapped to HTTP status here.
 */

use Clinic\Database\Connection;
use Clinic\Exception\ConflictException;
use Clinic\Exception\InvalidTransitionException;
use Clinic\Exception\ValidationException;
use Clinic\Http\Request;
use Clinic\Http\Response;
use Clinic\Http\Router;
use Clinic\Repository\AppointmentRepository;
use Clinic\Repository\AvailabilityRepository;
use Clinic\Repository\ProviderRepository;
use Clinic\Repository\StaffRepository;
use Clinic\Service\AuthService;
use Clinic\Service\AvailabilityService;
use Clinic\Service\BookingService;

require __DIR__ . '/../vendor/autoload.php';

// Load .env from backend/
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$pdo          = Connection::get();
$providerRepo = new ProviderRepository($pdo);
$apptRepo     = new AppointmentRepository($pdo);
$staffRepo    = new StaffRepository($pdo);
$bookingSvc   = new BookingService($pdo, $providerRepo, $apptRepo);
$availSvc     = new AvailabilityService($pdo, $apptRepo);
$authSvc      = new AuthService($staffRepo);

$router = new Router();

// -------- Auth (Phase 4) --------

$router->register('POST', '/api/auth/login', function (Request $req) use ($authSvc): void {
    $body     = $req->getBody();
    $username = isset($body['username']) ? trim((string) $body['username']) : '';
    $password = (string) ($body['password'] ?? '');

    if ($username === '' || $password === '') {
        Response::error('MISSING_FIELD', 'username and password are required', 400);
    }

    $result = $authSvc->login($username, $password);
    Response::success($result);
});

// -------- Staff schedule (Phase 4) — requires JWT --------

$router->register('GET', '/api/staff/schedule', function (Request $req) use ($authSvc, $apptRepo): void {
    $jwt = $authSvc->requireAuth();

    $date = $req->getQueryParam('date') ?? date('Y-m-d');

    // Doctors see only their own schedule; admin/receptionist see all
    if ($jwt->role === 'doctor') {
        $providerId = $jwt->provider_id ?? null;
        if ($providerId === null) {
            Response::error('CONFIGURATION_ERROR', 'Doctor account has no linked provider', 422);
        }
        $rows = $apptRepo->findByDateForStaff((string) $providerId, $date);
    } else {
        $rows = $apptRepo->findAllByDate($date);
    }

    Response::success([
        'date'         => $date,
        'role'         => $jwt->role,
        'appointments' => $rows,
    ]);
});

// -------- Bookings (Phase 1 + Phase 4 status patch) --------

$router->register('POST', '/api/bookings', function (Request $req) use ($bookingSvc): void {
    $result = $bookingSvc->create($req->getBody());
    Response::success($result, 201);
});

$router->register('GET', '/api/bookings/{id}', function (Request $req, array $params) use ($apptRepo): void {
    $row = $apptRepo->findById($params['id']);
    if ($row === null) {
        Response::error('BOOKING_NOT_FOUND', 'Booking not found', 404);
    }

    // Confirmation page: shape it nicely, exclude PII.
    Response::success([
        'id'         => $row['id'],
        'start_time' => $row['start_time'],
        'end_time'   => $row['end_time'],
        'status'     => $row['status'],
        'provider'   => [
            'id'        => $row['provider_id'],
            'name'      => $row['provider_name'],
            'specialty' => $row['provider_specialty'],
        ],
        'appointment_type' => [
            'id'               => $row['appointment_type_id'],
            'name'             => $row['type_name'],
            'duration_minutes' => (int) $row['type_duration_minutes'],
        ],
    ]);
});

$router->register('PATCH', '/api/bookings/{id}/status', function (Request $req, array $params) use ($authSvc, $apptRepo, $bookingSvc): void {
    $authSvc->requireAuth(); // must be logged in

    $row = $apptRepo->findById($params['id']);
    if ($row === null) {
        Response::error('BOOKING_NOT_FOUND', 'Booking not found', 404);
    }

    $body      = $req->getBody();
    $newStatus = isset($body['status']) ? trim((string) $body['status']) : '';
    if ($newStatus === '') {
        Response::error('MISSING_FIELD', 'status is required', 400);
    }

    // Validate the transition (throws InvalidTransitionException on failure)
    $bookingSvc->transition((string) $row['status'], $newStatus);

    $apptRepo->updateStatus($params['id'], $newStatus);

    Response::success([
        'id'     => $params['id'],
        'status' => $newStatus,
    ]);
});

// -------- Listing endpoints (Phase 2) --------

$router->register('GET', '/api/providers', function () use ($providerRepo): void {
    Response::success($providerRepo->findAll());
});

$router->register('GET', '/api/appointment-types', function () use ($pdo): void {
    $stmt = $pdo->query(
        'SELECT id, name, slug, duration_minutes
           FROM appointment_types
          WHERE is_active = 1
          ORDER BY duration_minutes'
    );
    Response::success($stmt->fetchAll());
});

// -------- Availability (Phase 2) --------

$router->register('GET', '/api/availability', function (Request $req) use ($availSvc): void {
    $providerId = $req->getQueryParam('provider_id');
    $typeId     = $req->getQueryParam('appointment_type_id');
    $date       = $req->getQueryParam('date');

    if ($providerId === null || $typeId === null || $date === null
        || $providerId === '' || $typeId === '' || $date === '') {
        Response::error(
            'MISSING_PARAMETER',
            'provider_id, appointment_type_id, and date are required',
            400,
        );
    }

    $payload = $availSvc->getSlots($providerId, $typeId, $date);
    Response::success($payload);
});

// -------- Dispatch + global error mapping --------

try {
    $router->dispatch(Request::fromGlobals());
} catch (ValidationException $e) {
    Response::error($e->getErrorCode(), $e->getMessage(), 422);
} catch (ConflictException $e) {
    Response::error($e->getErrorCode(), $e->getMessage(), 409);
} catch (InvalidTransitionException $e) {
    Response::error($e->getErrorCode(), $e->getMessage(), 422);
} catch (Throwable $e) {
    // Privacy: never echo $e->getMessage() to the client; it may contain SQL or PII.
    error_log('[index.php] Uncaught: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    Response::error('SERVER_ERROR', 'An internal error occurred', 500);
}
