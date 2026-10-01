"use client";

import { useQuery } from "@tanstack/react-query";
import { Activity, CircleCheck, CircleX, TriangleAlert } from "lucide-react";
import Link from "next/link";

import { Badge, Panel } from "@/components/ui/misc";
import { PageSpinner } from "@/components/ui/spinner";
import { api, errorMessage } from "@/lib/api";
import { cx } from "@/lib/cx";
import { formatDate, humanize } from "@/lib/format";
import { useCan } from "@/lib/session";
import type { AdminOverview, SystemHealth } from "@/lib/types";

function Stat({ label, value, href, tone }: { label: string; value: number | string; href?: string; tone?: "warning" }) {
  const body = (
    <Panel className={cx("p-4 transition-colors", href && "hover:border-border-strong")}>
      <p className="text-sm text-muted">{label}</p>
      <p className={cx("mt-1 text-2xl font-semibold tabular-nums", tone === "warning" && Number(value) > 0 && "text-warning")}>{value}</p>
    </Panel>
  );
  return href ? <Link href={href}>{body}</Link> : body;
}

export function AdminOverviewView() {
  const canHealth = useCan("system.health");
  const overview = useQuery({ queryKey: ["admin", "overview"], queryFn: () => api<AdminOverview>("admin/overview") });

  if (overview.isPending) return <PageSpinner />;
  if (overview.isError) return <p className="text-sm text-danger">{errorMessage(overview.error)}</p>;

  const o = overview.data;

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
        {o.reports && <Stat label="Open reports" value={o.reports.open} href="/admin/reports" tone="warning" />}
        {o.users && (
          <>
            <Stat label="Members" value={o.users.total} href="/admin/users" />
            <Stat label="New this week" value={o.users.new_7d} />
            <Stat label="Suspended" value={o.users.suspended} href="/admin/users?status=suspended" />
          </>
        )}
        <Stat label="Posts (24h)" value={o.content.posts_24h} />
        <Stat label="Comments (24h)" value={o.content.comments_24h} />
        {o.cards && (
          <>
            <Stat label="Active physical cards" value={o.cards.active} href="/admin/cards" />
            <Stat label="Replacement requests" value={o.cards.replacement_requests} href="/admin/cards?view=replacement" tone="warning" />
          </>
        )}
      </div>

      {canHealth && <HealthPanel />}

      <Panel className="p-5">
        <h2 className="text-sm font-semibold">Your permissions</h2>
        <div className="mt-3 flex flex-wrap gap-2">
          {o.permissions.map((p) => (
            <Badge key={p} tone="brand">
              {p}
            </Badge>
          ))}
        </div>
      </Panel>
    </div>
  );
}

const statusStyle = {
  ok: { icon: CircleCheck, className: "text-success", label: "Operational" },
  degraded: { icon: TriangleAlert, className: "text-warning", label: "Degraded" },
  down: { icon: CircleX, className: "text-danger", label: "Down" },
};

function HealthPanel() {
  const health = useQuery({
    queryKey: ["admin", "health"],
    queryFn: () => api<SystemHealth>("admin/system/health"),
    refetchInterval: 30_000,
  });

  return (
    <Panel className="overflow-hidden">
      <div className="flex items-center justify-between border-b border-border px-5 py-4">
        <h2 className="flex items-center gap-2 font-semibold">
          <Activity className="size-4 text-brand" aria-hidden /> System health
        </h2>
        {health.data && (
          <span className={cx("flex items-center gap-1.5 text-sm font-medium", statusStyle[health.data.status].className)}>
            {statusStyle[health.data.status].label}
          </span>
        )}
      </div>
      {health.isPending ? (
        <PageSpinner />
      ) : health.isError ? (
        <p className="px-5 py-6 text-sm text-danger">{errorMessage(health.error)}</p>
      ) : (
        <>
          <ul className="divide-y divide-border">
            {Object.entries(health.data.checks).map(([name, check]) => {
              const style = statusStyle[check.status];
              const details = Object.entries(check).filter(([key]) => !["status", "latency_ms", "error"].includes(key));
              return (
                <li key={name} className="flex items-center gap-3 px-5 py-3">
                  <style.icon className={cx("size-5", style.className)} aria-label={style.label} />
                  <span className="w-24 font-medium">{humanize(name)}</span>
                  <span className="flex-1 text-sm text-muted">
                    {details.map(([k, v]) => `${humanize(k)}: ${String(v)}`).join(" · ")}
                    {typeof check.error === "string" && <span className="text-danger">{check.error}</span>}
                  </span>
                  <span className="text-sm tabular-nums text-muted">{check.latency_ms} ms</span>
                </li>
              );
            })}
          </ul>
          <p className="border-t border-border px-5 py-3 text-xs text-muted">
            {health.data.app.environment} · Laravel {health.data.app.laravel} · PHP {health.data.app.php} · checked {formatDate(health.data.app.checked_at, true)}
          </p>
        </>
      )}
    </Panel>
  );
}
