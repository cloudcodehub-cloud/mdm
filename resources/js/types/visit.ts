export type ActiveVisitTask = {
    id: number;
    title: string;
    instructions: string | null;
    recurrence: string;
    recurrence_label: string;
    is_required: boolean;
    status: string;
    status_label: string;
};

export type ActiveVisitRecord = {
    id: number;
    status: string;
    status_label: string;
    service_type: string;
    clocked_in_at: string;
    clocked_in_at_label: string;
    location_method: string;
    location_status: string;
    location_status_label: string;
    unavailable_reason: string | null;
    latitude: string | null;
    longitude: string | null;
    accuracy: string | null;
    client: {
        id: number;
        name: string;
        client_number: string;
    };
    employee: {
        id: number;
        name: string;
        employee_number: string;
    };
    scheduled_visit: {
        id: number;
        service_date: string | null;
        time_label: string;
        shift_name: string | null;
        status: string;
    };
    tasks: ActiveVisitTask[];
};
