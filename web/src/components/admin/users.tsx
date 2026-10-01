"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, Search } from "lucide-react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { SelectField, TextArea } from "@/components/ui/field";
import { Badge, Panel } from "@/components/ui/misc";
import { PageSpinner } from "@/components/ui/spinner";
import { useToast } from "@/components/ui/toast";
import { api, errorMessage } from "@/lib/api";
import { formatDate, humanize } from "@/lib/format";
import { useCan, useMe } from "@/lib/session";
import type { AdminUserDetail, AdminUserSummary, PagedMeta, Role } from "@/lib/types";

import { NoAccess, Pager } from "./admin-nav";

const statusTone = { active: "success", deactivated: "neutral", suspended: "danger" } as const;

export function UsersView() {
  const allowed = useCan("users.view");
  const params = useSearchParams();
  const [input, setInput] = useState("");
  const [q, setQ] = useState("");
  const [status, setStatus] = useState(params.get("status") ?? "");
  const [page, setPage] = useState(1);

  useEffect(() => {
    const timer = setTimeout(() => (setQ(input.trim()), setPage(1)), 300);
    return () => clearTimeout(timer);
  }, [input]);

  const users = useQuery({
    queryKey: ["admin", "users", q, status, page],
    queryFn: () => api<{ data: AdminUserSummary[]; meta: PagedMeta }>(`admin/users?${new URLSearchParams({ ...(q && { q }), ...(status && { status }), page: String(page) })}`),
    enabled: allowed,
  });

  if (!allowed) return <NoAccess />;

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row">
        <div className="relative flex-1">
          <Search className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" aria-hidden />
          <input
            type="search"
            value={input}
            onChange={(e) => setInput(e.target.value)}
            placeholder="Search by email, username or name"
            aria-label="Search users"
            className="h-11 w-full rounded-xl border border-border bg-surface pl-10 pr-3 text-[15px] focus:border-brand focus:outline-none"
          />
        </div>
        <select
          value={status}
          onChange={(e) => (setStatus(e.target.value), setPage(1))}
          aria-label="Status"
          className="h-11 rounded-xl border border-border bg-surface px-3 text-[15px] focus:border-brand focus:outline-none"
        >
          <option value="">All statuses</option>
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
          <option value="deactivated">Deactivated</option>
        </select>
      </div>

      <Panel className="overflow-hidden">
        {users.isPending ? (
          <PageSpinner />
        ) : users.isError ? (
          <p className="p-5 text-sm text-danger">{errorMessage(users.error)}</p>
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="border-b border-border text-xs uppercase tracking-wide text-muted">
                  <tr>
                    <th className="px-5 py-3 font-medium">Member</th>
                    <th className="px-5 py-3 font-medium">Email</th>
                    <th className="px-5 py-3 font-medium">Status</th>
                    <th className="px-5 py-3 font-medium">Joined</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {users.data.data.map((user) => (
                    <tr key={user.id} className="hover:bg-surface-muted">
                      <td className="px-5 py-3">
                        <Link href={`/admin/users/${user.id}`} className="font-medium hover:underline">
                          {user.display_name ?? "—"}
                        </Link>
                        <p className="text-muted">@{user.username}</p>
                      </td>
                      <td className="px-5 py-3">
                        {user.email}
                        {!user.email_verified && <span className="ml-2 text-xs text-warning">unverified</span>}
                      </td>
                      <td className="px-5 py-3">
                        <Badge tone={statusTone[user.status]}>{humanize(user.status)}</Badge>
                      </td>
                      <td className="px-5 py-3 text-muted">{formatDate(user.created_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {users.data.data.length === 0 && <p className="p-8 text-center text-sm text-muted">No members match.</p>}
            <Pager page={page} lastPage={users.data.meta.last_page} onChange={setPage} />
          </>
        )}
      </Panel>
    </div>
  );
}

export function UserDetailView({ id }: { id: string }) {
  const me = useMe();
  const client = useQueryClient();
  const toast = useToast();
  const canSuspend = useCan("users.suspend");
  const canRoles = useCan("roles.manage");
  const [dialog, setDialog] = useState<"suspend" | "unsuspend" | null>(null);
  const [reason, setReason] = useState("");
  const [role, setRole] = useState("");

  const user = useQuery({ queryKey: ["admin", "user", id], queryFn: async () => (await api<{ data: AdminUserDetail }>(`admin/users/${id}`)).data });
  const roles = useQuery({ queryKey: ["admin", "roles"], queryFn: async () => (await api<{ data: Role[] }>("admin/roles")).data, enabled: canRoles });

  const refresh = () => client.invalidateQueries({ queryKey: ["admin"] });

  const suspension = useMutation({
    mutationFn: (action: "suspend" | "unsuspend") => api(`admin/users/${id}/${action}`, { json: { reason: reason || null } }),
    onSuccess: (_, action) => {
      refresh();
      setDialog(null);
      setReason("");
      toast.success(action === "suspend" ? "Account suspended." : "Account restored.");
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  const roleChange = useMutation({
    mutationFn: ({ slug, grant }: { slug: string; grant: boolean }) =>
      grant ? api(`admin/users/${id}/roles`, { json: { role: slug } }) : api(`admin/users/${id}/roles/${slug}`, { method: "DELETE" }),
    onSuccess: () => {
      refresh();
      setRole("");
      toast.success("Roles updated.");
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  if (user.isPending) return <PageSpinner />;
  if (user.isError) return <p className="text-sm text-danger">{errorMessage(user.error)}</p>;

  const u = user.data;
  const isSelf = u.id === me.id;

  return (
    <div className="space-y-5">
      <Link href="/admin/users" className="inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-text">
        <ArrowLeft className="size-4" /> All users
      </Link>

      <Panel className="p-5 sm:p-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-2">
              <h2 className="text-xl font-semibold">{u.display_name}</h2>
              <Badge tone={statusTone[u.status]}>{humanize(u.status)}</Badge>
            </div>
            <p className="text-muted">
              @{u.username} · {u.email} {!u.email_verified && <span className="text-warning">(unverified)</span>}
            </p>
          </div>
          {canSuspend && !isSelf && (
            <div className="flex gap-2">
              {u.status === "suspended" ? (
                <Button variant="outline" onClick={() => setDialog("unsuspend")}>
                  Restore account
                </Button>
              ) : (
                <Button variant="danger" onClick={() => setDialog("suspend")}>
                  Suspend
                </Button>
              )}
              {u.username && (
                <Link href={`/u/${u.username}`} className="inline-flex h-10 items-center rounded-full px-4 text-sm font-medium hover:bg-surface-muted">
                  View profile
                </Link>
              )}
            </div>
          )}
        </div>

        <dl className="mt-5 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
          {(
            [
              ["Posts", u.counts.posts],
              ["Followers", u.counts.followers],
              ["Reports against", u.counts.reports_against],
              ["Active sessions", u.counts.active_sessions],
            ] as const
          ).map(([label, value]) => (
            <div key={label} className="rounded-2xl bg-surface-muted p-3">
              <dt className="text-muted">{label}</dt>
              <dd className="mt-0.5 text-lg font-semibold tabular-nums">{value}</dd>
            </div>
          ))}
        </dl>

        <dl className="mt-5 grid grid-cols-[140px_1fr] gap-y-2 text-sm">
          <dt className="text-muted">Member ID</dt>
          <dd className="font-mono text-xs">{u.id}</dd>
          <dt className="text-muted">Joined</dt>
          <dd>{formatDate(u.created_at, true)}</dd>
          <dt className="text-muted">Last sign-in</dt>
          <dd>{formatDate(u.last_login_at, true)}</dd>
          <dt className="text-muted">Status changed</dt>
          <dd>{formatDate(u.status_changed_at, true)}</dd>
        </dl>
        <p className="mt-4 text-xs text-muted">Private profile details (legal name, phone, date of birth) are not available to administrators.</p>
      </Panel>

      <Panel className="p-5 sm:p-6">
        <h3 className="font-semibold">Cards</h3>
        {u.cards.length === 0 ? (
          <p className="mt-2 text-sm text-muted">No cards.</p>
        ) : (
          <ul className="mt-3 space-y-2 text-sm">
            {u.cards.map((card) => (
              <li key={card.id} className="flex items-center gap-3">
                <span className="font-mono">•••• {card.last4}</span>
                <Badge>{humanize(card.type)}</Badge>
                <Badge tone={card.status === "active" ? "success" : card.status === "pending_activation" ? "warning" : "danger"}>{humanize(card.status)}</Badge>
              </li>
            ))}
          </ul>
        )}
      </Panel>

      {canRoles && (
        <Panel className="p-5 sm:p-6">
          <h3 className="font-semibold">Administrative roles</h3>
          {isSelf && <p className="mt-1 text-sm text-muted">You can&apos;t change your own roles.</p>}
          <ul className="mt-3 flex flex-wrap gap-2">
            {u.roles.length === 0 && <li className="text-sm text-muted">None — a regular member.</li>}
            {u.roles.map((r) => (
              <li key={r.slug} className="inline-flex items-center gap-2 rounded-full bg-brand-soft py-1 pl-3 pr-1 text-sm text-brand">
                {r.name}
                {!isSelf && (
                  <button type="button" onClick={() => roleChange.mutate({ slug: r.slug, grant: false })} className="rounded-full px-2 py-0.5 text-xs hover:bg-brand hover:text-brand-fg" aria-label={`Remove ${r.name}`}>
                    Remove
                  </button>
                )}
              </li>
            ))}
          </ul>
          {!isSelf && (
            <div className="mt-4 flex items-end gap-2">
              <SelectField label="Grant a role" value={role} onChange={(e) => setRole(e.target.value)} className="flex-1">
                <option value="">Choose a role…</option>
                {roles.data
                  ?.filter((r) => !u.roles.some((ur) => ur.slug === r.slug))
                  .map((r) => (
                    <option key={r.slug} value={r.slug}>
                      {r.name} — {r.description}
                    </option>
                  ))}
              </SelectField>
              <Button className="h-11" disabled={!role} loading={roleChange.isPending} onClick={() => roleChange.mutate({ slug: role, grant: true })}>
                Grant
              </Button>
            </div>
          )}
        </Panel>
      )}

      <Dialog
        open={dialog !== null}
        onClose={() => setDialog(null)}
        title={dialog === "suspend" ? `Suspend @${u.username}?` : `Restore @${u.username}?`}
        description={dialog === "suspend" ? "They are signed out everywhere, can't sign in, and their posts are hidden. They receive an email." : "They can sign in again and their posts become visible."}
      >
        <div className="space-y-4">
          <TextArea label={dialog === "suspend" ? "Reason (required, kept in the audit trail)" : "Note (optional)"} value={reason} onChange={(e) => setReason(e.target.value)} maxLength={1000} rows={3} />
          <div className="flex justify-end gap-2">
            <Button variant="ghost" onClick={() => setDialog(null)}>
              Cancel
            </Button>
            <Button
              variant={dialog === "suspend" ? "danger" : "primary"}
              disabled={dialog === "suspend" && !reason.trim()}
              loading={suspension.isPending}
              onClick={() => dialog && suspension.mutate(dialog)}
            >
              {dialog === "suspend" ? "Suspend" : "Restore"}
            </Button>
          </div>
        </div>
      </Dialog>
    </div>
  );
}
