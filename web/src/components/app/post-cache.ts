"use client";

import type { InfiniteData, QueryClient } from "@tanstack/react-query";

import type { CursorPage, Post } from "@/lib/types";

type Lists = InfiniteData<CursorPage<Post>>;

// A post can appear in several cached lists (feeds, profile timelines, detail view).
export function updatePost(client: QueryClient, id: string, update: (post: Post) => Post | null) {
  for (const key of [["feed"], ["profile-posts"]]) {
    client.setQueriesData<Lists>({ queryKey: key }, (data) =>
      data
        ? {
            ...data,
            pages: data.pages.map((page) => ({
              ...page,
              data: page.data.flatMap((post) => {
                if (post.id !== id) return [post];
                const next = update(post);
                return next ? [next] : [];
              }),
            })),
          }
        : data,
    );
  }

  client.setQueryData<Post | null>(["post", id], (post) => (post ? update(post) : post));
}
