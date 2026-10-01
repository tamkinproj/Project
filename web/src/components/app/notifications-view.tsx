"use client";

import { useInfiniteQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Bell, Heart, MessageCircle, ShieldAlert, UserPlus } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import { Avatar } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { EmptyState, PageHeader, Panel, Segmented } from "@/components/ui/misc";
import { PageSpinner } from "@/components/ui/spinner";
import { api } from "@/lib/api";
import { cx } from "@/lib/cx";
import { timeAgo } from "@/lib/format";
import type { AppNotification, CursorPage } from "@/lib/types";

type Filter = "all" | "security";

function describe(n: AppNotification): { icon: typeof Bell; text: string; href: string | null } {
  const name = n.actor?.display_name ?? "Someone";
  switch (n.event) {
    case "followed":
      return { icon: UserPlus, text: `${name} started following you`, href: n.actor ? `/u/${n.actor.username}` : null };
    case "commented":
      return { icon: MessageCircle, text: `${name} commented on your post`, href: n.post_id ? `/posts/${n.post_id}` : null };
    case "reacted":
      return { icon: Heart, text: `${name} liked your post`, href: n.post_id ? `/posts/${n.post_id}` : null };
    default:
      return { icon: n.category === "security" ? ShieldAlert : Bell, text: n.message ?? "Account activity", href: n.event.startsWith("card_") ? "/card" : "/settings/security" };
  }
}

export function NotificationsView() {
  const client = useQueryClient();
  const [filter, setFilter] = useState<Filter>("all");

  const query = useInfiniteQuery({
    queryKey: ["notifications", "list", filter],
    queryFn: ({ pageParam }) => {
      const params = new URLSearchParams();
      if (filter === "security") params.set("category", "security");
      if (pageParam) params.set("cursor", pageParam);
      return api<CursorPage<AppNotification>>(`notifications?${params}`);
    },
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.meta.next_cursor,
  });

  const markAll = useMutation({
    mutationFn: () => api("notifications/read-all", { method: "POST" }),
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ["notifications"] });
    },
  });

  const hasUnread = query.data?.pages.some((page) => page.data.some((n) => !n.read_at));
  const { mutate: markAllRead } = markAll;

  // Opening the page marks everything as seen once the list has loaded.
  useEffect(() => {
    if (hasUnread) {
      const timer = setTimeout(() => markAllRead(), 2500);
      return () => clearTimeout(timer);
    }
  }, [hasUnread, markAllRead]);

  const items = query.data?.pages.flatMap((page) => page.data) ?? [];

  return (
    <div>
      <PageHeader
        title="Notifications"
        actions={
          hasUnread ? (
            <Button variant="ghost" size="sm" onClick={() => markAll.mutate()}>
              Mark all read
            </Button>
          ) : undefined
        }
      />
      <div className="mb-4">
        <Segmented
          label="Notification type"
          value={filter}
          onChange={setFilter}
          options={[
            { value: "all", label: "All" },
            { value: "security", label: "Security" },
          ]}
        />
      </div>

      <Panel className="overflow-hidden">
        {query.isPending ? (
          <PageSpinner />
        ) : items.length === 0 ? (
          <EmptyState icon={<Bell className="size-6" />} title="Nothing here yet">
            {filter === "security" ? "Sign-ins, password changes and card activity will appear here." : "Likes, comments and new followers will appear here."}
          </EmptyState>
        ) : (
          <ul>
            {items.map((n) => {
              const { icon: Icon, text, href } = describe(n);
              const security = n.category === "security";
              const content = (
                <div className={cx("flex gap-3.5 px-5 py-4", !n.read_at && (security ? "bg-warning-soft/60" : "bg-brand-soft/50"))}>
                  <div className={cx("mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full", security ? "bg-warning-soft text-warning" : "bg-brand-soft text-brand")}>
                    <Icon className="size-[18px]" aria-hidden />
                  </div>
                  <div className="min-w-0 flex-1">
                    {security && <p className="text-xs font-semibold uppercase tracking-wide text-warning">Security</p>}
                    <p className="text-[15px] leading-snug">{text}</p>
                    <p className="mt-0.5 text-xs text-muted">{timeAgo(n.created_at)}</p>
                  </div>
                  {n.actor && <Avatar name={n.actor.display_name} src={n.actor.avatar_url} size="sm" />}
                </div>
              );

              return (
                <li key={n.id} className="border-b border-border last:border-b-0">
                  {href ? (
                    <Link href={href} className="block hover:bg-surface-muted">
                      {content}
                    </Link>
                  ) : (
                    content
                  )}
                </li>
              );
            })}
          </ul>
        )}
        {query.hasNextPage && (
          <div className="flex justify-center py-3">
            <Button variant="ghost" size="sm" loading={query.isFetchingNextPage} onClick={() => query.fetchNextPage()}>
              Load more
            </Button>
          </div>
        )}
      </Panel>
    </div>
  );
}
