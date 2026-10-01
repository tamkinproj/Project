"use client";

import { useInfiniteQuery, useMutation, useQuery, useQueryClient, type InfiniteData } from "@tanstack/react-query";
import { ArrowLeft, Flag, MessageCircle, Trash2 } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";

import { Avatar } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Menu } from "@/components/ui/menu";
import { EmptyState, Panel } from "@/components/ui/misc";
import { PageSpinner } from "@/components/ui/spinner";
import { useToast } from "@/components/ui/toast";
import { ApiError, api, errorMessage } from "@/lib/api";
import { timeAgo } from "@/lib/format";
import { useMe } from "@/lib/session";
import type { Comment, CursorPage, Post } from "@/lib/types";

import { ReportDialog } from "./dialogs";
import { updatePost } from "./post-cache";
import { PostCard } from "./post-card";

export function PostView({ id }: { id: string }) {
  const router = useRouter();
  const post = useQuery({
    queryKey: ["post", id],
    queryFn: async () => (await api<{ data: Post }>(`posts/${id}`)).data,
  });

  return (
    <div className="space-y-4">
      <button type="button" onClick={() => router.back()} className="inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-text">
        <ArrowLeft className="size-4" /> Back
      </button>

      {post.isPending ? (
        <PageSpinner />
      ) : post.isError || !post.data ? (
        <Panel>
          <EmptyState icon={<MessageCircle className="size-6" />} title="Post not available">
            {post.error instanceof ApiError && post.error.status === 404 ? "It may have been deleted, or you may not have permission to see it." : errorMessage(post.error)}
          </EmptyState>
        </Panel>
      ) : (
        <>
          <Panel className="overflow-hidden">
            <PostCard post={post.data} linkToPost={false} />
          </Panel>
          <Comments post={post.data} />
        </>
      )}
    </div>
  );
}

function Comments({ post }: { post: Post }) {
  const me = useMe();
  const client = useQueryClient();
  const toast = useToast();
  const [body, setBody] = useState("");
  const [reporting, setReporting] = useState<string | null>(null);

  const comments = useInfiniteQuery({
    queryKey: ["comments", post.id],
    queryFn: ({ pageParam }) => api<CursorPage<Comment>>(`posts/${post.id}/comments${pageParam ? `?cursor=${encodeURIComponent(pageParam)}` : ""}`),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.meta.next_cursor,
  });

  const add = useMutation({
    mutationFn: () => api<{ data: Comment }>(`posts/${post.id}/comments`, { json: { body } }),
    onSuccess: ({ data: created }) => {
      setBody("");
      updatePost(client, post.id, (p) => ({ ...p, counts: { ...p.counts, comments: p.counts.comments + 1 } }));
      // Show the new comment immediately, then reconcile with the server.
      client.setQueryData<InfiniteData<CursorPage<Comment>>>(["comments", post.id], (data) =>
        data
          ? { ...data, pages: data.pages.map((page, i) => (i === data.pages.length - 1 && !page.data.some((c) => c.id === created.id) ? { ...page, data: [...page.data, created] } : page)) }
          : data,
      );
      client.invalidateQueries({ queryKey: ["comments", post.id] });
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  const remove = useMutation({
    mutationFn: (commentId: string) => api(`comments/${commentId}`, { method: "DELETE" }),
    onSuccess: () => {
      updatePost(client, post.id, (p) => ({ ...p, counts: { ...p.counts, comments: Math.max(0, p.counts.comments - 1) } }));
      client.invalidateQueries({ queryKey: ["comments", post.id] });
      toast.success("Comment deleted.");
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  const items = comments.data?.pages.flatMap((page) => page.data) ?? [];

  return (
    <Panel className="overflow-hidden">
      <h2 className="border-b border-border px-5 py-3.5 text-sm font-semibold">Comments</h2>

      {comments.isPending ? (
        <PageSpinner />
      ) : items.length === 0 ? (
        <p className="px-5 py-8 text-center text-sm text-muted">No comments yet. Start the conversation.</p>
      ) : (
        <ul>
          {items.map((comment) => (
            <li key={comment.id} className="flex gap-3 border-b border-border px-5 py-3.5">
              <Link href={`/u/${comment.author.username}`}>
                <Avatar name={comment.author.display_name} src={comment.author.avatar_url} size="sm" />
              </Link>
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-1.5 text-sm">
                  <Link href={`/u/${comment.author.username}`} className="font-semibold hover:underline">
                    {comment.author.display_name}
                  </Link>
                  <span className="text-muted">· {timeAgo(comment.created_at)}</span>
                </div>
                <p className="mt-0.5 whitespace-pre-wrap break-words text-[15px] leading-relaxed" dir="auto">
                  {comment.body}
                </p>
              </div>
              <div className="-mr-2">
                <Menu
                  label="Comment options"
                  items={[
                    ...(comment.viewer.can_delete ? [{ label: "Delete comment", icon: <Trash2 className="size-4" />, danger: true, onSelect: () => remove.mutate(comment.id) }] : []),
                    ...(comment.author.username !== me.profile.username ? [{ label: "Report comment", icon: <Flag className="size-4" />, onSelect: () => setReporting(comment.id) }] : []),
                  ]}
                />
              </div>
            </li>
          ))}
        </ul>
      )}

      {comments.hasNextPage && (
        <div className="flex justify-center py-3">
          <Button variant="ghost" size="sm" loading={comments.isFetchingNextPage} onClick={() => comments.fetchNextPage()}>
            Show more comments
          </Button>
        </div>
      )}

      {me.email_verified ? (
        <form
          onSubmit={(event: FormEvent) => {
            event.preventDefault();
            if (body.trim()) add.mutate();
          }}
          className="flex items-end gap-3 px-5 py-4"
        >
          <Avatar name={me.profile.display_name} src={me.profile.avatar_url} size="sm" />
          <textarea
            value={body}
            onChange={(e) => setBody(e.target.value)}
            placeholder="Write a comment…"
            aria-label="Write a comment"
            rows={1}
            maxLength={2000}
            dir="auto"
            className="min-h-10 flex-1 resize-none rounded-2xl border border-border bg-surface-muted px-4 py-2.5 text-[15px] focus:border-brand focus:outline-none"
          />
          <Button type="submit" disabled={!body.trim()} loading={add.isPending}>
            Reply
          </Button>
        </form>
      ) : (
        <p className="px-5 py-4 text-center text-sm text-muted">Verify your email to comment.</p>
      )}

      {reporting && <ReportDialog open onClose={() => setReporting(null)} targetType="comment" targetId={reporting} subject="comment" />}
    </Panel>
  );
}
