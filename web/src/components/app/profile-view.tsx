"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Ban, CalendarDays, Flag, MapPin, UserRound } from "lucide-react";

import { useState } from "react";

import { Avatar } from "@/components/ui/avatar";
import { Button, ButtonLink } from "@/components/ui/button";
import { Menu } from "@/components/ui/menu";
import { Badge, EmptyState, Panel } from "@/components/ui/misc";
import { PageSpinner } from "@/components/ui/spinner";
import { useToast } from "@/components/ui/toast";
import { ApiError, api, errorMessage } from "@/lib/api";
import { formatMonth } from "@/lib/format";
import { useMe } from "@/lib/session";
import type { PublicProfile } from "@/lib/types";

import { ConfirmDialog, ReportDialog } from "./dialogs";
import { PostList } from "./post-list";

export function ProfileView({ username }: { username: string }) {
  const me = useMe();
  const client = useQueryClient();
  const toast = useToast();
  const [dialog, setDialog] = useState<"block" | "report" | null>(null);

  const profile = useQuery({
    queryKey: ["profile", username],
    queryFn: async () => (await api<{ data: PublicProfile }>(`profiles/${encodeURIComponent(username)}`)).data,
  });

  const refresh = () => {
    client.invalidateQueries({ queryKey: ["profile", username] });
    client.invalidateQueries({ queryKey: ["profile-posts", username] });
    client.invalidateQueries({ queryKey: ["feed"] });
  };

  const follow = useMutation({
    mutationFn: (following: boolean) => api(`profiles/${username}/follow`, { method: following ? "DELETE" : "POST" }),
    onSuccess: refresh,
    onError: (error) => toast.error(errorMessage(error)),
  });

  const block = useMutation({
    mutationFn: (blocked: boolean) => api(`profiles/${username}/block`, { method: blocked ? "DELETE" : "POST" }),
    onSuccess: (_, blocked) => {
      refresh();
      setDialog(null);
      toast.success(blocked ? `You unblocked @${username}.` : `You blocked @${username}.`);
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  if (profile.isPending) return <PageSpinner />;

  if (profile.isError) {
    return (
      <Panel>
        <EmptyState icon={<UserRound className="size-6" />} title="This profile isn't available">
          {profile.error instanceof ApiError && profile.error.status === 404 ? "The account may not exist, or it may not be visible to you." : errorMessage(profile.error)}
        </EmptyState>
      </Panel>
    );
  }

  const p = profile.data;
  const rel = p.relationship!;

  return (
    <div className="space-y-5">
      <Panel className="p-5 sm:p-6">
        <div className="flex items-start justify-between gap-4">
          <Avatar name={p.display_name} src={p.avatar_url} size="xl" />
          <div className="flex items-center gap-2">
            {rel.is_self ? (
              <ButtonLink href="/settings" variant="outline">
                Edit profile
              </ButtonLink>
            ) : rel.has_blocked ? (
              <Button variant="outline" onClick={() => block.mutate(true)} loading={block.isPending}>
                Unblock
              </Button>
            ) : (
              <>
                {(rel.can_follow || rel.is_following) && me.email_verified && (
                  <Button variant={rel.is_following ? "outline" : "primary"} onClick={() => follow.mutate(rel.is_following)} loading={follow.isPending}>
                    {rel.is_following ? "Following" : "Follow"}
                  </Button>
                )}
                <Menu
                  label="Profile options"
                  items={[
                    { label: "Report account", icon: <Flag className="size-4" />, onSelect: () => setDialog("report") },
                    { label: `Block @${p.username}`, icon: <Ban className="size-4" />, danger: true, onSelect: () => setDialog("block") },
                  ]}
                />
              </>
            )}
          </div>
        </div>

        <div className="mt-4">
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-2xl font-semibold tracking-tight">{p.display_name}</h1>
            {rel.follows_you && <Badge>Follows you</Badge>}
            {rel.has_blocked && <Badge tone="danger">Blocked</Badge>}
          </div>
          <p className="text-muted">@{p.username}</p>
          {p.bio && (
            <p className="mt-3 whitespace-pre-wrap text-[15px] leading-relaxed" dir="auto">
              {p.bio}
            </p>
          )}
          <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
            {p.location && (
              <span className="inline-flex items-center gap-1.5">
                <MapPin className="size-4" aria-hidden /> {p.location}
              </span>
            )}
            <span className="inline-flex items-center gap-1.5">
              <CalendarDays className="size-4" aria-hidden /> Joined {formatMonth(p.joined_at)}
            </span>
          </div>
          {p.counts && (
            <dl className="mt-4 flex gap-5 text-sm">
              {(
                [
                  ["posts", "Posts"],
                  ["followers", "Followers"],
                  ["following", "Following"],
                ] as const
              ).map(([key, label]) => (
                <div key={key} className="flex gap-1.5">
                  <dt className="order-2 text-muted">{label}</dt>
                  <dd className="font-semibold">{p.counts![key]}</dd>
                </div>
              ))}
            </dl>
          )}
        </div>
      </Panel>

      <Panel className="overflow-hidden">
        {rel.has_blocked ? (
          <p className="px-5 py-10 text-center text-sm text-muted">You blocked this account. Unblock to see their posts.</p>
        ) : (
          <PostList
            queryKey={["profile-posts", username]}
            path={`profiles/${encodeURIComponent(username)}/posts`}
            empty={<p className="px-5 py-10 text-center text-sm text-muted">No posts to show yet.</p>}
          />
        )}
      </Panel>

      <ConfirmDialog
        open={dialog === "block"}
        onClose={() => setDialog(null)}
        onConfirm={() => block.mutate(false)}
        pending={block.isPending}
        title={`Block @${p.username}?`}
        description="You won't see each other's posts or profiles, and any follows between you are removed. They aren't notified."
        confirmLabel="Block"
        danger
      />
      <ReportDialog open={dialog === "report"} onClose={() => setDialog(null)} targetType="user" targetId={p.username} subject={`@${p.username}`} />
    </div>
  );
}
