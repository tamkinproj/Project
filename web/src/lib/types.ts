export type Visibility = "public" | "followers" | "only_me";

export type Privacy = {
  profile_searchable: boolean;
  show_location: boolean;
  who_can_follow: "everyone" | "nobody";
  who_can_message: "everyone" | "followers" | "nobody";
  default_post_visibility: Visibility;
};

export type Me = {
  id: string;
  email: string;
  email_verified: boolean;
  status: "active" | "deactivated" | "suspended";
  created_at: string;
  profile: {
    username: string;
    display_name: string;
    bio: string | null;
    location: string | null;
    avatar_url: string | null;
  };
  privacy: Privacy;
  permissions: Permission[];
};

export type Permission =
  | "users.view"
  | "users.suspend"
  | "reports.review"
  | "content.moderate"
  | "cards.issue"
  | "cards.manage"
  | "audit.view"
  | "roles.manage"
  | "system.health";

export type Author = {
  username: string;
  display_name: string;
  avatar_url: string | null;
};

export type PublicProfile = Author & {
  bio: string | null;
  location: string | null;
  joined_at: string;
  counts?: { followers: number; following: number; posts: number };
  relationship?: {
    is_self: boolean;
    is_following: boolean;
    follows_you: boolean;
    has_blocked: boolean;
    can_follow: boolean;
  };
};

export type Media = { id: string; url: string; width: number; height: number };

export type Post = {
  id: string;
  body: string | null;
  visibility: Visibility;
  author: Author;
  media: Media[];
  counts: { comments: number; reactions: number };
  viewer: { reaction: string | null; can_edit: boolean; can_delete: boolean };
  created_at: string;
  edited_at: string | null;
};

export type Comment = {
  id: string;
  body: string;
  author: Author;
  created_at: string;
  viewer: { can_delete: boolean };
};

export type CursorPage<T> = {
  data: T[];
  meta: { next_cursor: string | null };
};

export type AppNotification = {
  id: string;
  category: "social" | "security";
  event: string;
  message: string | null;
  actor: Author | null;
  post_id: string | null;
  read_at: string | null;
  created_at: string;
};

export type CardStatus = "pending_activation" | "active" | "frozen" | "lost" | "replaced" | "revoked";

export type MemberCard = {
  id: string;
  type: "virtual" | "physical";
  status: CardStatus;
  last4: string;
  activated_at: string | null;
  frozen_at: string | null;
  lost_reported_at: string | null;
  replacement_requested_at: string | null;
  created_at: string;
  actions: { freeze: boolean; unfreeze: boolean; report_lost: boolean; request_replacement: boolean };
};

export type CardEvent = { id: string; event: string; created_at: string };

export type Session = {
  id: string;
  device_name: string;
  ip_address: string | null;
  user_agent: string | null;
  last_used_at: string | null;
  created_at: string;
  expires_at: string | null;
  is_current: boolean;
};

export type PrivateProfile = {
  legal_name: string | null;
  phone: string | null;
  date_of_birth: string | null;
};

// Administration

export type PagedMeta = { current_page: number; last_page: number; total: number };

export type AdminPerson = { id: string; username: string | null; display_name: string; status: string } | null;

export type AdminReport = {
  id: string;
  reason: string;
  details: string | null;
  status: "open" | "actioned" | "dismissed";
  created_at: string;
  reporter: AdminPerson;
  reviewer: AdminPerson;
  reviewed_at: string | null;
  resolution_note: string | null;
  target: {
    type: "post" | "comment" | "user";
    id: string;
    exists: boolean;
    author?: AdminPerson;
    body?: string | null;
    media?: { url: string; width: number; height: number }[];
    visibility?: Visibility;
    moderation_status?: "visible" | "hidden";
    account_status?: string;
    post_id?: string;
  };
};

export type AdminUserSummary = {
  id: string;
  email: string;
  email_verified: boolean;
  username: string | null;
  display_name: string | null;
  status: "active" | "deactivated" | "suspended";
  created_at: string;
};

export type AdminUserDetail = AdminUserSummary & {
  last_login_at: string | null;
  status_changed_at: string | null;
  roles: { slug: string; name: string }[];
  counts: { posts: number; followers: number; reports_against: number; active_sessions: number };
  cards: { id: string; type: string; status: CardStatus; last4: string }[];
};

export type AdminCard = {
  id: string;
  type: "virtual" | "physical";
  status: CardStatus;
  last4: string;
  holder: { id: string; username: string | null; display_name: string | null } | null;
  issued_by: string | null;
  replaces_card_id: string | null;
  activation_expires_at: string | null;
  activated_at: string | null;
  replacement_requested_at: string | null;
  revoked_at: string | null;
  created_at: string;
};

export type Role = {
  slug: string;
  name: string;
  description: string | null;
  permissions: { name: Permission; label: string }[];
};

export type AuditEntry = {
  id: string;
  action: string;
  actor_type: string;
  actor_id: string | null;
  subject_type: string | null;
  subject_id: string | null;
  ip_address: string | null;
  user_agent: string | null;
  request_id: string | null;
  metadata: Record<string, unknown> | null;
  created_at: string;
};

export type HealthCheck = { status: "ok" | "degraded" | "down"; latency_ms: number; [key: string]: unknown };

export type SystemHealth = {
  status: "ok" | "degraded" | "down";
  checks: Record<string, HealthCheck>;
  app: Record<string, string>;
};

export type AdminOverview = {
  users: { total: number; active: number; suspended: number; new_7d: number } | null;
  reports: { open: number } | null;
  content: { posts_24h: number; comments_24h: number };
  cards: { active: number; pending: number; replacement_requests: number } | null;
  permissions: Permission[];
};
