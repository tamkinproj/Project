"use client";

import { useQuery } from "@tanstack/react-query";
import { Search } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import { Avatar } from "@/components/ui/avatar";
import { EmptyState, PageHeader, Panel } from "@/components/ui/misc";
import { PageSpinner } from "@/components/ui/spinner";
import { api } from "@/lib/api";
import type { Author } from "@/lib/types";

export function SearchView() {
  const [input, setInput] = useState("");
  const [term, setTerm] = useState("");

  useEffect(() => {
    const timer = setTimeout(() => setTerm(input.trim()), 250);
    return () => clearTimeout(timer);
  }, [input]);

  const results = useQuery({
    queryKey: ["search", term],
    queryFn: ({ signal }) => api<{ data: Author[] }>(`profiles/search?q=${encodeURIComponent(term)}`, { signal }),
    enabled: term.length > 0,
  });

  return (
    <div>
      <PageHeader title="Search" description="Find people by name or @username. People who opt out of search won't appear." />
      <div className="relative mb-5">
        <Search className="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-muted" aria-hidden />
        <input
          type="search"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="Search people"
          aria-label="Search people"
          autoFocus
          maxLength={50}
          className="h-12 w-full rounded-full border border-border bg-surface pl-12 pr-4 text-[15px] shadow-card focus:border-brand focus:outline-none focus:ring-4 focus:ring-[var(--ring)]"
        />
      </div>

      <Panel className="overflow-hidden">
        {!term ? (
          <EmptyState icon={<Search className="size-6" />} title="Search the community">
            Try a display name like &ldquo;Abu&rdquo; or a username.
          </EmptyState>
        ) : results.isPending ? (
          <PageSpinner />
        ) : results.data?.data.length === 0 ? (
          <p className="px-5 py-10 text-center text-sm text-muted">No one found for &ldquo;{term}&rdquo;.</p>
        ) : (
          <ul>
            {results.data?.data.map((person) => (
              <li key={person.username} className="border-b border-border last:border-b-0">
                <Link href={`/u/${person.username}`} className="flex items-center gap-3 px-5 py-3.5 hover:bg-surface-muted">
                  <Avatar name={person.display_name} src={person.avatar_url} />
                  <div className="min-w-0">
                    <p className="truncate font-semibold">{person.display_name}</p>
                    <p className="truncate text-sm text-muted">@{person.username}</p>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </Panel>
    </div>
  );
}
