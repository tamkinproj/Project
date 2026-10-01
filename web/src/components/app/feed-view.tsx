"use client";

import { Sparkles, Users } from "lucide-react";
import { useSearchParams } from "next/navigation";
import { useState } from "react";

import { ButtonLink } from "@/components/ui/button";
import { EmptyState, Panel, Segmented } from "@/components/ui/misc";
import { useMe } from "@/lib/session";

import { Composer } from "./composer";
import { PostList } from "./post-list";

type Scope = "following" | "everyone";

export function FeedView() {
  const me = useMe();
  const params = useSearchParams();
  const [scope, setScope] = useState<Scope>("following");

  return (
    <div className="space-y-5">
      {params.get("welcome") && (
        <Panel className="flex items-start gap-4 p-5">
          <div className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-soft text-brand">
            <Sparkles className="size-5" aria-hidden />
          </div>
          <div>
            <h2 className="font-semibold">Welcome, {me.profile.display_name}.</h2>
            <p className="mt-1 text-sm text-muted">Your digital member card is ready. Add a photo and a short bio so people recognise you.</p>
            <div className="mt-3 flex gap-2">
              <ButtonLink href="/settings" size="sm">
                Complete profile
              </ButtonLink>
              <ButtonLink href="/card" size="sm" variant="outline">
                See my card
              </ButtonLink>
            </div>
          </div>
        </Panel>
      )}

      <Composer />

      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold tracking-tight">Feed</h1>
        <Segmented
          label="Feed"
          value={scope}
          onChange={setScope}
          options={[
            { value: "following", label: "Following" },
            { value: "everyone", label: "Everyone" },
          ]}
        />
      </div>

      <Panel className="overflow-hidden">
        <PostList
          key={scope}
          queryKey={["feed", scope]}
          path={`feed?scope=${scope}`}
          empty={
            scope === "following" ? (
              <EmptyState icon={<Users className="size-6" />} title="Your feed is quiet">
                Follow people to see their posts here, or switch to <strong>Everyone</strong> to discover the community.
              </EmptyState>
            ) : (
              <EmptyState icon={<Sparkles className="size-6" />} title="No posts yet">
                Be the first to share something.
              </EmptyState>
            )
          }
        />
      </Panel>
    </div>
  );
}
