"use client";

import { useInfiniteQuery } from "@tanstack/react-query";
import { useEffect, useRef, type ReactNode } from "react";

import { Button } from "@/components/ui/button";
import { PageSpinner, Spinner } from "@/components/ui/spinner";
import { api, errorMessage } from "@/lib/api";
import type { CursorPage, Post } from "@/lib/types";

import { PostCard } from "./post-card";

export function usePostPages(queryKey: readonly unknown[], path: string) {
  return useInfiniteQuery({
    queryKey,
    queryFn: ({ pageParam, signal }) => {
      const separator = path.includes("?") ? "&" : "?";
      return api<CursorPage<Post>>(pageParam ? `${path}${separator}cursor=${encodeURIComponent(pageParam)}` : path, { signal });
    },
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.meta.next_cursor,
  });
}

export function PostList({ queryKey, path, empty }: { queryKey: readonly unknown[]; path: string; empty: ReactNode }) {
  const query = usePostPages(queryKey, path);
  const sentinel = useRef<HTMLDivElement>(null);
  const { hasNextPage, isFetchingNextPage, fetchNextPage } = query;

  useEffect(() => {
    const node = sentinel.current;
    if (!node || !hasNextPage) return;
    const observer = new IntersectionObserver(([entry]) => entry.isIntersecting && !isFetchingNextPage && fetchNextPage(), { rootMargin: "600px" });
    observer.observe(node);
    return () => observer.disconnect();
  }, [hasNextPage, isFetchingNextPage, fetchNextPage]);

  if (query.isPending) return <PageSpinner />;

  if (query.isError) {
    return (
      <div className="px-5 py-10 text-center text-sm text-muted">
        <p>{errorMessage(query.error)}</p>
        <Button variant="outline" size="sm" className="mt-3" onClick={() => query.refetch()}>
          Try again
        </Button>
      </div>
    );
  }

  const posts = query.data.pages.flatMap((page) => page.data);

  if (posts.length === 0) return <>{empty}</>;

  return (
    <div>
      {posts.map((post) => (
        <PostCard key={post.id} post={post} />
      ))}
      <div ref={sentinel} className="flex justify-center py-6 text-muted">
        {isFetchingNextPage ? <Spinner /> : !hasNextPage && posts.length > 5 ? <span className="text-sm">You&apos;re all caught up.</span> : null}
      </div>
    </div>
  );
}
