export interface StaffInfo {
  id: string;
  name: string;
  role: 'admin' | 'receptionist' | 'doctor';
  provider_id: string | null;
}

export interface LoginResponse {
  token: string;
  staff: StaffInfo;
}

export interface ScheduleAppointment {
  id: string;
  start_time: string;
  end_time: string;
  status: string;
  patient_name: string;
  patient_phone: string;
  patient_notes: string | null;
  type_name: string;
  type_duration_minutes: number;
  /** Only present when role is admin/receptionist (all-providers view) */
  provider_name?: string;
}

export interface ScheduleResponse {
  date: string;
  role: string;
  appointments: ScheduleAppointment[];
}
