"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Ban, Flag, Globe2, Heart, Lock, MessageCircle, Pencil, Trash2, Users } from "lucide-react";
import Link from "next/link";
import { useState } from "react";

import { Avatar } from "@/components/ui/avatar";
import { Menu, type MenuItem } from "@/components/ui/menu";
import { useToast } from "@/components/ui/toast";
import { api, errorMessage } from "@/lib/api";
import { cx } from "@/lib/cx";
import { formatDate, timeAgo } from "@/lib/format";
import type { Media, Post, Visibility } from "@/lib/types";

import { ConfirmDialog, EditPostDialog, ReportDialog } from "./dialogs";
import { updatePost } from "./post-cache";

const visibilityMeta: Record<Visibility, { icon: typeof Globe2; label: string }> = {
  public: { icon: Globe2, label: "Public" },
  followers: { icon: Users, label: "Followers only" },
  only_me: { icon: Lock, label: "Only you" },
};

export function MediaGrid({ media }: { media: Media[] }) {
  if (media.length === 0) return null;

  if (media.length === 1) {
    const [item] = media;
    return (
      <a href={item.url} target="_blank" rel="noopener noreferrer" className="mt-3 block overflow-hidden rounded-2xl border border-border bg-surface-muted">
        {/* eslint-disable-next-line @next/next/no-img-element -- signed, short-lived media URLs */}
        <img src={item.url} alt="" loading="lazy" width={item.width} height={item.height} className="max-h-[520px] w-full object-cover" />
      </a>
    );
  }

  return (
    <div className={cx("mt-3 grid gap-1 overflow-hidden rounded-2xl border border-border", "grid-cols-2")}>
      {media.map((item, index) => (
        <a key={item.id} href={item.url} target="_blank" rel="noopener noreferrer" className={cx("block bg-surface-muted", media.length === 3 && index === 0 && "row-span-2")}>
          {/* eslint-disable-next-line @next/next/no-img-element -- signed, short-lived media URLs */}
          <img src={item.url} alt="" loading="lazy" className="aspect-square size-full object-cover" />
        </a>
      ))}
    </div>
  );
}

export function PostCard({ post, linkToPost = true }: { post: Post; linkToPost?: boolean }) {
  const client = useQueryClient();
  const toast = useToast();
  const [dialog, setDialog] = useState<"edit" | "delete" | "report" | "block" | null>(null);
  const liked = post.viewer.reaction !== null;
  const Visibility = visibilityMeta[post.visibility];

  const react = useMutation({
    mutationFn: () => api<{ reaction: string | null; reactions_count: number }>(`posts/${post.id}/reaction`, { method: liked ? "DELETE" : "PUT" }),
    onMutate: () =>
      updatePost(client, post.id, (p) => ({
        ...p,
        viewer: { ...p.viewer, reaction: liked ? null : "like" },
        counts: { ...p.counts, reactions: p.counts.reactions + (liked ? -1 : 1) },
      })),
    onSuccess: (result) =>
      updatePost(client, post.id, (p) => ({ ...p, viewer: { ...p.viewer, reaction: result.reaction }, counts: { ...p.counts, reactions: result.reactions_count } })),
    onError: (error) => {
      updatePost(client, post.id, () => post);
      toast.error(errorMessage(error));
    },
  });

  const remove = useMutation({
    mutationFn: () => api(`posts/${post.id}`, { method: "DELETE" }),
    onSuccess: () => {
      updatePost(client, post.id, () => null);
      toast.success("Post deleted.");
      setDialog(null);
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  const block = useMutation({
    mutationFn: () => api(`profiles/${post.author.username}/block`, { method: "POST" }),
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ["feed"] });
      toast.success(`You blocked @${post.author.username}.`);
      setDialog(null);
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  const menu: MenuItem[] = post.viewer.can_edit
    ? [
        { label: "Edit post", icon: <Pencil className="size-4" />, onSelect: () => setDialog("edit") },
        { label: "Delete post", icon: <Trash2 className="size-4" />, onSelect: () => setDialog("delete"), danger: true },
      ]
    : [
        { label: "Report post", icon: <Flag className="size-4" />, onSelect: () => setDialog("report") },
        { label: `Block @${post.author.username}`, icon: <Ban className="size-4" />, onSelect: () => setDialog("block"), danger: true },
      ];

  return (
    <article className="border-b border-border px-4 py-4 last:border-b-0 sm:px-5">
      <div className="flex gap-3">
        <Link href={`/u/${post.author.username}`} className="shrink-0">
          <Avatar name={post.author.display_name} src={post.author.avatar_url} />
        </Link>
        <div className="min-w-0 flex-1">
          <div className="flex items-start justify-between gap-2">
            <div className="flex min-w-0 flex-wrap items-center gap-x-1.5 text-[15px] leading-tight">
              <Link href={`/u/${post.author.username}`} className="truncate font-semibold hover:underline">
                {post.author.display_name}
              </Link>
              <span className="truncate text-muted">@{post.author.username}</span>
              <span className="text-muted" aria-hidden>
                ·
              </span>
              <Link href={`/posts/${post.id}`} className="text-sm text-muted hover:underline">
                <time dateTime={post.created_at} title={formatDate(post.created_at, true)}>
                  {timeAgo(post.created_at)}
                </time>
              </Link>
              <Visibility.icon className="size-3.5 text-muted" aria-label={Visibility.label} />
              {post.edited_at && <span className="text-xs text-muted">(edited)</span>}
            </div>
            <div className="-mr-2 -mt-1.5">
              <Menu items={menu} label="Post options" />
            </div>
          </div>

          {post.body &&
            (linkToPost ? (
              <Link href={`/posts/${post.id}`} className="mt-1.5 block whitespace-pre-wrap break-words text-[15px] leading-relaxed" dir="auto">
                {post.body}
              </Link>
            ) : (
              <p className="mt-1.5 whitespace-pre-wrap break-words text-[17px] leading-relaxed" dir="auto">
                {post.body}
              </p>
            ))}

          <MediaGrid media={post.media} />

          <div className="mt-3 flex items-center gap-1 text-muted">
            <button
              type="button"
              onClick={() => react.mutate()}
              aria-pressed={liked}
              aria-label={liked ? "Remove like" : "Like"}
              className={cx("-ml-2 inline-flex items-center gap-1.5 rounded-full px-2 py-1.5 text-sm transition-colors hover:bg-danger-soft hover:text-danger", liked && "text-danger")}
            >
              <Heart className={cx("size-[18px]", liked && "fill-current")} />
              {post.counts.reactions > 0 && post.counts.reactions}
            </button>
            <Link href={`/posts/${post.id}`} aria-label="Comments" className="inline-flex items-center gap-1.5 rounded-full px-2 py-1.5 text-sm transition-colors hover:bg-brand-soft hover:text-brand">
              <MessageCircle className="size-[18px]" />
              {post.counts.comments > 0 && post.counts.comments}
            </Link>
          </div>
        </div>
      </div>

      {post.viewer.can_edit && dialog === "edit" && (
        <EditPostDialog post={post} open onClose={() => setDialog(null)} onSaved={(updated) => updatePost(client, post.id, () => ({ ...updated }))} />
      )}
      <ConfirmDialog
        open={dialog === "delete"}
        onClose={() => setDialog(null)}
        onConfirm={() => remove.mutate()}
        pending={remove.isPending}
        title="Delete this post?"
        description="It will be removed for everyone, along with its photos and comments. This can't be undone."
        confirmLabel="Delete"
        danger
      />
      <ConfirmDialog
        open={dialog === "block"}
        onClose={() => setDialog(null)}
        onConfirm={() => block.mutate()}
        pending={block.isPending}
        title={`Block @${post.author.username}?`}
        description="You won't see each other's posts or profiles, and they won't be able to follow or interact with you. They aren't notified."
        confirmLabel="Block"
        danger
      />
      <ReportDialog open={dialog === "report"} onClose={() => setDialog(null)} targetType="post" targetId={post.id} subject="post" />
    </article>
  );
}
