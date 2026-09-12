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
    other_user: MessagingUser | null;
    latest_message: string | null;
    latest_at: string | null;
    unread_count: number;
};

export type ConversationDetail = {
    id: number;
    other_user: MessagingUser | null;
    first_unread_id?: number | null;
};

export type ChatMessage = {
    id: number;
    body: string;
    sender_id: number;
    sender_name: string;
    is_mine?: boolean;
    created_at: string | null;
    created_on?: string | null;
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
    type: 'message' | 'announcement' | 'schedule' | 'availability' | 'profile';
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
