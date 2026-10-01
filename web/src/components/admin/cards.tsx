"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Copy, IdCard, Plus, TriangleAlert } from "lucide-react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useState, type FormEvent } from "react";

import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { TextArea, TextField } from "@/components/ui/field";
import { Badge, EmptyState, Panel, Segmented } from "@/components/ui/misc";
import { PageSpinner } from "@/components/ui/spinner";
import { useToast } from "@/components/ui/toast";
import { ApiError, api, errorMessage } from "@/lib/api";
import { formatDate, humanize } from "@/lib/format";
import { useCan } from "@/lib/session";
import type { AdminCard, CardStatus, PagedMeta } from "@/lib/types";

import { NoAccess, Pager } from "./admin-nav";

type View = "all" | "pending_activation" | "active" | "replacement";

const tone: Record<CardStatus, "success" | "warning" | "danger" | "neutral" | "brand"> = {
  active: "success",
  pending_activation: "warning",
  frozen: "brand",
  lost: "danger",
  revoked: "danger",
  replaced: "neutral",
};

type Issued = { data: AdminCard; secrets: { card_number: string; activation_code: string; notice: string } };

export function CardsAdminView() {
  const allowed = useCan("cards.manage");
  const canIssue = useCan("cards.issue");
  const params = useSearchParams();
  const [view, setView] = useState<View>(params.get("view") === "replacement" ? "replacement" : "all");
  const [q, setQ] = useState("");
  const [page, setPage] = useState(1);
  const [issuing, setIssuing] = useState<{ replaces?: AdminCard } | null>(null);
  const [revoking, setRevoking] = useState<AdminCard | null>(null);

  const query = new URLSearchParams({ page: String(page) });
  if (view === "replacement") query.set("replacement_requested", "1");
  else if (view !== "all") query.set("status", view);
  if (q.trim()) query.set("q", q.trim());

  const cards = useQuery({
    queryKey: ["admin", "cards", view, q, page],
    queryFn: () => api<{ data: AdminCard[]; meta: PagedMeta }>(`admin/cards?${query}`),
    enabled: allowed,
  });

  if (!allowed && !canIssue) return <NoAccess />;

  return (
    <div className="space-y-4">
      <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <Segmented
          label="Card filter"
          value={view}
          onChange={(v) => (setView(v), setPage(1))}
          options={[
            { value: "all", label: "All" },
            { value: "pending_activation", label: "Pending" },
            { value: "active", label: "Active" },
            { value: "replacement", label: "Replacements" },
          ]}
        />
        {canIssue && (
          <Button icon={<Plus className="size-4" />} onClick={() => setIssuing({})}>
            Issue card
          </Button>
        )}
      </div>

      <input
        type="search"
        value={q}
        onChange={(e) => (setQ(e.target.value), setPage(1))}
        placeholder="Username or last 4 digits"
        aria-label="Search cards"
        className="h-11 w-full rounded-xl border border-border bg-surface px-3.5 text-[15px] focus:border-brand focus:outline-none"
      />

      <Panel className="overflow-hidden">
        {!allowed ? (
          <p className="p-6 text-sm text-muted">You can issue cards, but listing them requires the card management permission.</p>
        ) : cards.isPending ? (
          <PageSpinner />
        ) : cards.isError ? (
          <p className="p-5 text-sm text-danger">{errorMessage(cards.error)}</p>
        ) : cards.data.data.length === 0 ? (
          <EmptyState icon={<IdCard className="size-6" />} title="No cards">
            Physical cards you issue appear here.
          </EmptyState>
        ) : (
          <>
            <ul className="divide-y divide-border">
              {cards.data.data.map((card) => (
                <li key={card.id} className="flex flex-wrap items-center gap-4 px-5 py-4">
                  <span className="font-mono text-sm">•••• {card.last4}</span>
                  <Badge tone={tone[card.status]}>{humanize(card.status)}</Badge>
                  {card.replacement_requested_at && card.status !== "replaced" && card.status !== "revoked" && <Badge tone="warning">Replacement requested</Badge>}
                  <div className="min-w-0 flex-1 text-sm">
                    {card.holder ? (
                      <Link href={`/admin/users/${card.holder.id}`} className="font-medium hover:underline">
                        {card.holder.display_name} <span className="font-normal text-muted">@{card.holder.username}</span>
                      </Link>
                    ) : (
                      <span className="text-muted">Unassigned</span>
                    )}
                    <p className="text-xs text-muted">
                      Issued {formatDate(card.created_at)}
                      {card.issued_by && ` by @${card.issued_by}`}
                      {card.status === "pending_activation" && card.activation_expires_at && ` · code expires ${formatDate(card.activation_expires_at)}`}
                    </p>
                  </div>
                  <div className="flex gap-2">
                    {canIssue && card.replacement_requested_at && !["replaced", "revoked"].includes(card.status) && (
                      <Button size="sm" variant="outline" onClick={() => setIssuing({ replaces: card })}>
                        Issue replacement
                      </Button>
                    )}
                    {card.status !== "revoked" && card.status !== "replaced" && (
                      <Button size="sm" variant="ghost" className="text-danger" onClick={() => setRevoking(card)}>
                        Revoke
                      </Button>
                    )}
                  </div>
                </li>
              ))}
            </ul>
            <Pager page={page} lastPage={cards.data.meta.last_page} onChange={setPage} />
          </>
        )}
      </Panel>

      {issuing && <IssueCardDialog replaces={issuing.replaces} onClose={() => setIssuing(null)} />}
      {revoking && <RevokeDialog card={revoking} onClose={() => setRevoking(null)} />}
    </div>
  );
}

function IssueCardDialog({ replaces, onClose }: { replaces?: AdminCard; onClose: () => void }) {
  const client = useQueryClient();
  const [chipUid, setChipUid] = useState("");
  const [username, setUsername] = useState(replaces?.holder?.username ?? "");
  const [issued, setIssued] = useState<Issued | null>(null);

  const issue = useMutation({
    mutationFn: () => api<Issued>("admin/cards", { json: { chip_uid: chipUid, username: username || null, replaces_card_id: replaces?.id ?? null } }),
    onSuccess: (data) => {
      setIssued(data);
      client.invalidateQueries({ queryKey: ["admin"] });
    },
  });

  const fieldError = (name: string) => (issue.error instanceof ApiError ? issue.error.field(name) : undefined);

  if (issued) {
    return (
      <Dialog open onClose={onClose} title="Card issued" description="Print these on the card mailer now. For security they are not stored and cannot be shown again.">
        <div className="space-y-3">
          <Secret label="Card number" value={issued.secrets.card_number} />
          <Secret label="Activation code" value={issued.secrets.activation_code} />
          <p className="flex gap-2 rounded-xl bg-warning-soft px-3 py-2.5 text-sm text-warning">
            <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden /> The member links the card with the activation code and the last four digits.
          </p>
          <div className="flex justify-end">
            <Button onClick={onClose}>I&apos;ve recorded them</Button>
          </div>
        </div>
      </Dialog>
    );
  }

  return (
    <Dialog open onClose={onClose} title={replaces ? `Replace card •••• ${replaces.last4}` : "Issue a physical card"} description="Tap the blank card on your reader to capture its chip UID.">
      <form
        onSubmit={(event: FormEvent) => {
          event.preventDefault();
          issue.mutate();
        }}
        className="space-y-4"
      >
        {issue.error && !(issue.error instanceof ApiError && Object.keys(issue.error.errors).length) && <p className="text-sm text-danger">{errorMessage(issue.error)}</p>}
        <TextField label="Chip UID" value={chipUid} onChange={(e) => setChipUid(e.target.value)} placeholder="04:A2:2B:1A:9C:5D:80" className="font-mono" autoComplete="off" autoFocus error={fieldError("chip_uid")} />
        <TextField
          label="Assign to member (optional)"
          value={username}
          onChange={(e) => setUsername(e.target.value)}
          placeholder="username"
          hint="If set, only this member can activate the card."
          disabled={!!replaces}
          error={fieldError("username")}
        />
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={!chipUid.trim()} loading={issue.isPending}>
            Issue card
          </Button>
        </div>
      </form>
    </Dialog>
  );
}

function Secret({ label, value }: { label: string; value: string }) {
  const toast = useToast();
  return (
    <div className="flex items-center justify-between gap-3 rounded-2xl border border-border bg-surface-muted px-4 py-3">
      <div>
        <p className="text-xs text-muted">{label}</p>
        <p className="font-mono text-lg tracking-wider">{value}</p>
      </div>
      <Button
        size="icon"
        variant="ghost"
        aria-label={`Copy ${label}`}
        onClick={() => navigator.clipboard.writeText(value).then(() => toast.success(`${label} copied.`))}
      >
        <Copy className="size-4" />
      </Button>
    </div>
  );
}

function RevokeDialog({ card, onClose }: { card: AdminCard; onClose: () => void }) {
  const client = useQueryClient();
  const toast = useToast();
  const [reason, setReason] = useState("");

  const revoke = useMutation({
    mutationFn: () => api(`admin/cards/${card.id}/revoke`, { json: { reason } }),
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ["admin"] });
      toast.success("Card revoked.");
      onClose();
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  return (
    <Dialog open onClose={onClose} title={`Revoke card •••• ${card.last4}?`} description="The card stops working everywhere immediately and permanently. The holder is notified.">
      <div className="space-y-4">
        <TextArea label="Reason (kept in the audit trail)" value={reason} onChange={(e) => setReason(e.target.value)} maxLength={500} rows={3} />
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button variant="danger" disabled={!reason.trim()} loading={revoke.isPending} onClick={() => revoke.mutate()}>
            Revoke card
          </Button>
        </div>
      </div>
    </Dialog>
  );
}
