import type { AppRole } from '@/types/auth';

export type MessagingUser = {
    id: number;
    name: string;
    email: string;
    role: AppRole;
    can_message: boolean;
};

export type ConversationSummary = {
    id: number;
    type: 'direct' | 'group';
    name: string;
    other_user: MessagingUser | null;
    participant_count?: number;
    is_active_participant?: boolean;
    latest_message: string | null;
    latest_at: string | null;
    unread_count: number;
};

export type ConversationDetail = {
    id: number;
    type: 'direct' | 'group';
    name: string;
    other_user: MessagingUser | null;
    participants?: MessagingUser[];
    created_by_user_id?: number | null;
    can_manage?: boolean;
    is_active_participant?: boolean;
    admin_review?: boolean;
    first_unread_id?: number | null;
};

export type ChatAttachment = {
    id: number;
    original_name: string;
    mime_type: string | null;
    size_bytes: number;
};

export type ChatMessage = {
    id: number;
    body: string;
    message_type?: 'user' | 'system';
    is_system?: boolean;
    sender_id: number | null;
    sender_name: string;
    is_mine?: boolean;
    created_at: string | null;
    created_on?: string | null;
    attachments?: ChatAttachment[];
};

export type AnnouncementRecord = {
    id: number;
    title: string;
    body: string;
    audience: string;
    audience_label: string;
    author_name: string;
    published_at: string;
    expires_at: string | null;
    is_active: boolean;
    is_read: boolean;
    can_update: boolean;
};

export type InboxNotification = {
    id: number;
    type:
        | 'message'
        | 'announcement'
        | 'schedule'
        | 'availability'
        | 'availability_override'
        | 'staffing_urgency'
        | 'profile'
        | 'support_ticket'
        | 'compliance'
        | 'incident'
        | 'material_correction';
    title: string;
    body: string;
    url: string | null;
    source_key: string;
    read_at: string | null;
    created_at: string | null;
};

export type InboxActivity = {
    unread_messages: number;
    unread_notifications: number;
    notifications: InboxNotification[];
    toasts?: InboxNotification[];
};
