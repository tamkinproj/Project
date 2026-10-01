"use client";

import { useInfiniteQuery } from "@tanstack/react-query";
import { Fragment, useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { Panel } from "@/components/ui/misc";
import { PageSpinner } from "@/components/ui/spinner";
import { api, errorMessage } from "@/lib/api";
import { formatDate } from "@/lib/format";
import { useCan } from "@/lib/session";
import type { AuditEntry, CursorPage } from "@/lib/types";

import { NoAccess } from "./admin-nav";

const presets = ["", "auth.", "account.", "card.", "moderation.", "admin."];

export function AuditView() {
  const allowed = useCan("audit.view");
  const [input, setInput] = useState("");
  const [action, setAction] = useState("");
  const [open, setOpen] = useState<string | null>(null);

  useEffect(() => {
    const timer = setTimeout(() => setAction(input.trim()), 300);
    return () => clearTimeout(timer);
  }, [input]);

  const logs = useInfiniteQuery({
    queryKey: ["admin", "audit", action],
    queryFn: ({ pageParam }) => {
      const params = new URLSearchParams();
      if (action) params.set("action", action);
      if (pageParam) params.set("cursor", pageParam);
      return api<CursorPage<AuditEntry>>(`admin/audit-logs?${params}`);
    },
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.meta.next_cursor,
    enabled: allowed,
  });

  if (!allowed) return <NoAccess />;

  const rows = logs.data?.pages.flatMap((p) => p.data) ?? [];

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <input
          type="search"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="Filter by action prefix, e.g. card."
          aria-label="Filter by action"
          className="h-10 min-w-64 flex-1 rounded-xl border border-border bg-surface px-3.5 font-mono text-sm focus:border-brand focus:outline-none"
        />
        {presets.map((p) => (
          <button key={p || "all"} type="button" onClick={() => setInput(p)} className="rounded-full bg-surface px-3 py-1.5 font-mono text-xs text-muted hover:text-text">
            {p || "all"}
          </button>
        ))}
      </div>

      <Panel className="overflow-hidden">
        {logs.isPending ? (
          <PageSpinner />
        ) : logs.isError ? (
          <p className="p-5 text-sm text-danger">{errorMessage(logs.error)}</p>
        ) : rows.length === 0 ? (
          <p className="p-8 text-center text-sm text-muted">No entries.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-border text-xs uppercase tracking-wide text-muted">
                <tr>
                  <th className="px-5 py-3 font-medium">Time</th>
                  <th className="px-5 py-3 font-medium">Action</th>
                  <th className="px-5 py-3 font-medium">Actor</th>
                  <th className="px-5 py-3 font-medium">Subject</th>
                  <th className="px-5 py-3 font-medium">IP</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {rows.map((row) => (
                  <Fragment key={row.id}>
                    <tr onClick={() => setOpen(open === row.id ? null : row.id)} className="cursor-pointer hover:bg-surface-muted" aria-expanded={open === row.id}>
                      <td className="whitespace-nowrap px-5 py-2.5 text-muted">{formatDate(row.created_at, true)}</td>
                      <td className="px-5 py-2.5 font-mono text-xs">{row.action}</td>
                      <td className="px-5 py-2.5 font-mono text-xs text-muted">{row.actor_id ? `${row.actor_type}:${row.actor_id.slice(-8)}` : row.actor_type}</td>
                      <td className="px-5 py-2.5 font-mono text-xs text-muted">{row.subject_type ? `${row.subject_type}:${row.subject_id?.slice(-8)}` : "—"}</td>
                      <td className="px-5 py-2.5 font-mono text-xs text-muted">{row.ip_address ?? "—"}</td>
                    </tr>
                    {open === row.id && (
                      <tr className="bg-surface-muted/60">
                        <td colSpan={5} className="px-5 py-3">
                          <pre className="overflow-x-auto whitespace-pre-wrap break-all font-mono text-xs text-muted">
                            {JSON.stringify({ actor_id: row.actor_id, subject_id: row.subject_id, request_id: row.request_id, user_agent: row.user_agent, metadata: row.metadata }, null, 2)}
                          </pre>
                        </td>
                      </tr>
                    )}
                  </Fragment>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {logs.hasNextPage && (
          <div className="flex justify-center border-t border-border py-3">
            <Button variant="ghost" size="sm" loading={logs.isFetchingNextPage} onClick={() => logs.fetchNextPage()}>
              Load older entries
            </Button>
          </div>
        )}
      </Panel>
      <p className="text-xs text-muted">The audit log is append-only: entries cannot be edited or deleted, even by administrators.</p>
    </div>
  );
}
