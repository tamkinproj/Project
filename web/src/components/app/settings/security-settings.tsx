"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Laptop, Smartphone } from "lucide-react";
import Link from "next/link";
import { useState, type FormEvent } from "react";

import { Avatar } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { TextField } from "@/components/ui/field";
import { Badge, Panel } from "@/components/ui/misc";
import { PageSpinner } from "@/components/ui/spinner";
import { useToast } from "@/components/ui/toast";
import { ApiError, api, errorMessage } from "@/lib/api";
import { timeAgo } from "@/lib/format";
import type { Author, Session } from "@/lib/types";

export function SecuritySettings() {
  return (
    <div className="space-y-5">
      <PasswordPanel />
      <SessionsPanel />
      <BlockedPanel />
    </div>
  );
}

function PasswordPanel() {
  const toast = useToast();
  const client = useQueryClient();
  const [form, setForm] = useState({ current_password: "", password: "", password_confirmation: "" });

  const change = useMutation({
    mutationFn: () => api<{ message: string }>("me/password", { method: "PUT", json: form }),
    onSuccess: (data) => {
      setForm({ current_password: "", password: "", password_confirmation: "" });
      client.invalidateQueries({ queryKey: ["sessions"] });
      toast.success(data.message);
    },
    onError: (error) => !(error instanceof ApiError && error.status === 422) && toast.error(errorMessage(error)),
  });

  const fieldError = (name: string) => (change.error instanceof ApiError ? change.error.field(name) : undefined);

  return (
    <Panel className="p-5 sm:p-6">
      <h2 className="font-semibold">Change password</h2>
      <p className="mt-1 text-sm text-muted">Your other devices will be signed out.</p>
      <form
        onSubmit={(event: FormEvent) => {
          event.preventDefault();
          change.mutate();
        }}
        className="mt-4 space-y-4"
      >
        <TextField label="Current password" type="password" autoComplete="current-password" value={form.current_password} onChange={(e) => setForm({ ...form, current_password: e.target.value })} error={fieldError("current_password")} />
        <TextField label="New password" type="password" autoComplete="new-password" hint="At least 10 characters." value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} error={fieldError("password")} />
        <TextField label="Confirm new password" type="password" autoComplete="new-password" value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} />
        <div className="flex justify-end">
          <Button type="submit" loading={change.isPending} disabled={!form.current_password || !form.password}>
            Update password
          </Button>
        </div>
      </form>
    </Panel>
  );
}

function SessionsPanel() {
  const client = useQueryClient();
  const toast = useToast();
  const sessions = useQuery({ queryKey: ["sessions"], queryFn: async () => (await api<{ data: Session[] }>("me/sessions")).data });

  const revoke = useMutation({
    mutationFn: (id: string | null) => api(id ? `me/sessions/${id}` : "me/sessions", { method: "DELETE" }),
    onSuccess: (_, id) => {
      client.invalidateQueries({ queryKey: ["sessions"] });
      toast.success(id ? "Device signed out." : "All other devices signed out.");
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  const others = sessions.data?.filter((s) => !s.is_current).length ?? 0;

  return (
    <Panel className="overflow-hidden">
      <div className="flex items-center justify-between gap-4 p-5 sm:p-6">
        <div>
          <h2 className="font-semibold">Where you&apos;re signed in</h2>
          <p className="mt-1 text-sm text-muted">Sign out any device you don&apos;t recognise, then change your password.</p>
        </div>
        {others > 0 && (
          <Button variant="outline" size="sm" onClick={() => revoke.mutate(null)} loading={revoke.isPending && revoke.variables === null}>
            Sign out others
          </Button>
        )}
      </div>
      {sessions.isPending ? (
        <PageSpinner />
      ) : (
        <ul className="border-t border-border">
          {sessions.data?.map((session) => {
            const mobile = /Android|iOS|Mobile app/.test(session.device_name);
            const Icon = mobile ? Smartphone : Laptop;
            return (
              <li key={session.id} className="flex items-center gap-4 border-b border-border px-5 py-4 last:border-b-0 sm:px-6">
                <div className="flex size-10 items-center justify-center rounded-full bg-surface-muted text-muted">
                  <Icon className="size-5" aria-hidden />
                </div>
                <div className="min-w-0 flex-1">
                  <p className="flex items-center gap-2 font-medium">
                    {session.device_name}
                    {session.is_current && <Badge tone="success">This device</Badge>}
                  </p>
                  <p className="text-sm text-muted">
                    {session.ip_address ?? "Unknown IP"} · {session.last_used_at ? `Active ${timeAgo(session.last_used_at)}` : `Signed in ${timeAgo(session.created_at)}`}
                  </p>
                </div>
                {!session.is_current && (
                  <Button variant="ghost" size="sm" onClick={() => revoke.mutate(session.id)} loading={revoke.isPending && revoke.variables === session.id}>
                    Sign out
                  </Button>
                )}
              </li>
            );
          })}
        </ul>
      )}
    </Panel>
  );
}

function BlockedPanel() {
  const client = useQueryClient();
  const toast = useToast();
  const blocked = useQuery({ queryKey: ["blocks"], queryFn: async () => (await api<{ data: Author[] }>("me/blocks")).data });

  const unblock = useMutation({
    mutationFn: (username: string) => api(`profiles/${username}/block`, { method: "DELETE" }),
    onSuccess: (_, username) => {
      client.invalidateQueries({ queryKey: ["blocks"] });
      client.invalidateQueries({ queryKey: ["profile", username] });
      toast.success(`Unblocked @${username}.`);
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  return (
    <Panel className="overflow-hidden">
      <div className="p-5 sm:p-6">
        <h2 className="font-semibold">Blocked accounts</h2>
        <p className="mt-1 text-sm text-muted">Blocked people can&apos;t see your profile or posts, follow you or interact with you.</p>
      </div>
      {blocked.isPending ? (
        <PageSpinner />
      ) : blocked.data?.length === 0 ? (
        <p className="border-t border-border px-5 py-6 text-sm text-muted sm:px-6">You haven&apos;t blocked anyone.</p>
      ) : (
        <ul className="border-t border-border">
          {blocked.data?.map((person) => (
            <li key={person.username} className="flex items-center gap-3 border-b border-border px-5 py-3.5 last:border-b-0 sm:px-6">
              <Avatar name={person.display_name} src={person.avatar_url} size="sm" />
              <Link href={`/u/${person.username}`} className="min-w-0 flex-1">
                <p className="truncate font-medium">{person.display_name}</p>
                <p className="truncate text-sm text-muted">@{person.username}</p>
              </Link>
              <Button variant="outline" size="sm" onClick={() => unblock.mutate(person.username)} loading={unblock.isPending && unblock.variables === person.username}>
                Unblock
              </Button>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  );
}
