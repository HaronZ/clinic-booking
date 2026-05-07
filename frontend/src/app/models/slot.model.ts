export interface Slot {
  start_time: string; // 'HH:mm'
  end_time: string;   // 'HH:mm'
  available: boolean;
}

export interface AvailabilityResponse {
  provider_id: string;
  date: string; // 'YYYY-MM-DD'
  slot_interval_minutes: number;
  slots: Slot[];
}
