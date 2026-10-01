"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Flag } from "lucide-react";
import Link from "next/link";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { TextArea } from "@/components/ui/field";
import { Badge, EmptyState, Panel, Segmented } from "@/components/ui/misc";
import { PageSpinner } from "@/components/ui/spinner";
import { useToast } from "@/components/ui/toast";
import { api, errorMessage } from "@/lib/api";
import { formatDate, humanize, timeAgo } from "@/lib/format";
import { useCan } from "@/lib/session";
import type { AdminReport, PagedMeta } from "@/lib/types";

import { NoAccess, Pager } from "./admin-nav";

type Status = "open" | "actioned" | "dismissed";
type Decision = "dismiss" | "hide_content" | "suspend_user";

export function ReportsView() {
  const allowed = useCan("reports.review");
  const [status, setStatus] = useState<Status>("open");
  const [page, setPage] = useState(1);

  const reports = useQuery({
    queryKey: ["admin", "reports", status, page],
    queryFn: () => api<{ data: AdminReport[]; meta: PagedMeta }>(`admin/reports?status=${status}&page=${page}`),
    enabled: allowed,
  });

  if (!allowed) return <NoAccess />;

  return (
    <div className="space-y-4">
      <Segmented
        label="Report status"
        value={status}
        onChange={(value) => (setStatus(value), setPage(1))}
        options={[
          { value: "open", label: "Open" },
          { value: "actioned", label: "Actioned" },
          { value: "dismissed", label: "Dismissed" },
        ]}
      />

      {reports.isPending ? (
        <PageSpinner />
      ) : reports.isError ? (
        <p className="text-sm text-danger">{errorMessage(reports.error)}</p>
      ) : reports.data.data.length === 0 ? (
        <Panel>
          <EmptyState icon={<Flag className="size-6" />} title={status === "open" ? "Queue is clear" : "Nothing here"}>
            {status === "open" ? "New reports from members will appear here, oldest first." : null}
          </EmptyState>
        </Panel>
      ) : (
        <Panel className="overflow-hidden">
          <ul className="divide-y divide-border">
            {reports.data.data.map((report) => (
              <ReportRow key={report.id} report={report} />
            ))}
          </ul>
          <Pager page={page} lastPage={reports.data.meta.last_page} onChange={setPage} />
        </Panel>
      )}
    </div>
  );
}

function ReportRow({ report }: { report: AdminReport }) {
  const client = useQueryClient();
  const toast = useToast();
  const canModerate = useCan("content.moderate");
  const canSuspend = useCan("users.suspend");
  const [decision, setDecision] = useState<Decision | null>(null);
  const [note, setNote] = useState("");
  const target = report.target;

  const resolve = useMutation({
    mutationFn: () => api(`admin/reports/${report.id}/resolve`, { json: { decision, note: note || null } }),
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ["admin"] });
      toast.success("Report resolved.");
      setDecision(null);
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  return (
    <li className="p-5">
      <div className="flex flex-wrap items-center gap-2 text-sm">
        <Badge tone="danger">{humanize(report.reason)}</Badge>
        <Badge>{humanize(target.type)}</Badge>
        <span className="text-muted">
          reported by {report.reporter?.username ? `@${report.reporter.username}` : "a deleted account"} · {timeAgo(report.created_at)}
        </span>
      </div>
      {report.details && <p className="mt-2 text-sm italic text-muted">&ldquo;{report.details}&rdquo;</p>}

      <div className="mt-3 rounded-2xl border border-border bg-surface-muted/60 p-4">
        {!target.exists ? (
          <p className="text-sm text-muted">The reported {target.type} no longer exists.</p>
        ) : (
          <>
            <div className="flex flex-wrap items-center gap-2 text-sm">
              {target.author && (
                <Link href={`/admin/users/${target.author.id}`} className="font-semibold hover:underline">
                  {target.author.display_name} {target.author.username && <span className="font-normal text-muted">@{target.author.username}</span>}
                </Link>
              )}
              {target.moderation_status === "hidden" && <Badge tone="warning">Hidden</Badge>}
              {target.account_status && target.account_status !== "active" && <Badge tone="warning">{humanize(target.account_status)}</Badge>}
              {target.visibility && <Badge>{humanize(target.visibility)}</Badge>}
            </div>
            {target.body && (
              <p className="mt-2 whitespace-pre-wrap break-words text-[15px]" dir="auto">
                {target.body}
              </p>
            )}
            {target.media && target.media.length > 0 && (
              <div className="mt-3 flex gap-2">
                {target.media.map((m) => (
                  // eslint-disable-next-line @next/next/no-img-element -- signed media URLs
                  <img key={m.url} src={m.url} alt="" className="size-20 rounded-xl object-cover" />
                ))}
              </div>
            )}
          </>
        )}
      </div>

      {report.status === "open" ? (
        <div className="mt-3 flex flex-wrap gap-2">
          <Button size="sm" variant="outline" onClick={() => setDecision("dismiss")}>
            Dismiss
          </Button>
          {canModerate && target.exists && target.type !== "user" && (
            <Button size="sm" variant="outline" onClick={() => setDecision("hide_content")}>
              Hide {target.type}
            </Button>
          )}
          {canSuspend && target.exists && (
            <Button size="sm" variant="danger" onClick={() => setDecision("suspend_user")}>
              Suspend {target.type === "user" ? "account" : "author"}
            </Button>
          )}
        </div>
      ) : (
        <p className="mt-3 text-sm text-muted">
          {humanize(report.status)} by {report.reviewer?.username ? `@${report.reviewer.username}` : "a moderator"} on {formatDate(report.reviewed_at, true)}
          {report.resolution_note && <> — &ldquo;{report.resolution_note}&rdquo;</>}
        </p>
      )}

      <Dialog
        open={decision !== null}
        onClose={() => setDecision(null)}
        title={decision === "dismiss" ? "Dismiss report" : decision === "hide_content" ? `Hide this ${target.type}` : "Suspend account"}
        description={
          decision === "suspend_user"
            ? "The account is signed out everywhere and cannot sign in until restored. They receive an email."
            : decision === "hide_content"
              ? "The content disappears for everyone. Other open reports about it are closed."
              : "No action is taken against the content."
        }
      >
        <div className="space-y-4">
          <TextArea label="Note for the audit trail" value={note} onChange={(e) => setNote(e.target.value)} maxLength={1000} rows={3} />
          <div className="flex justify-end gap-2">
            <Button variant="ghost" onClick={() => setDecision(null)}>
              Cancel
            </Button>
            <Button variant={decision === "dismiss" ? "primary" : "danger"} onClick={() => resolve.mutate()} loading={resolve.isPending}>
              Confirm
            </Button>
          </div>
        </div>
      </Dialog>
    </li>
  );
}
