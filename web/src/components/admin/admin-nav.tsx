"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

import { cx } from "@/lib/cx";
import { useMe } from "@/lib/session";
import type { Permission } from "@/lib/types";

const sections: { href: string; label: string; permission?: Permission }[] = [
  { href: "/admin", label: "Overview" },
  { href: "/admin/reports", label: "Reports", permission: "reports.review" },
  { href: "/admin/users", label: "Users", permission: "users.view" },
  { href: "/admin/cards", label: "Cards", permission: "cards.manage" },
  { href: "/admin/audit", label: "Audit log", permission: "audit.view" },
];

export function AdminNav() {
  const me = useMe();
  const pathname = usePathname();
  const visible = sections.filter((s) => !s.permission || me.permissions.includes(s.permission));

  return (
    <nav aria-label="Administration" className="-mx-4 mb-6 overflow-x-auto px-4 sm:mx-0 sm:px-0">
      <ul className="flex gap-1.5">
        {visible.map((section) => {
          const active = section.href === "/admin" ? pathname === "/admin" : pathname.startsWith(section.href);
          return (
            <li key={section.href}>
              <Link
                href={section.href}
                aria-current={active ? "page" : undefined}
                className={cx("block whitespace-nowrap rounded-full px-4 py-2 text-sm font-medium transition-colors", active ? "bg-text text-bg" : "bg-surface text-muted hover:text-text")}
              >
                {section.label}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}

export function Pager({ page, lastPage, onChange }: { page: number; lastPage: number; onChange: (page: number) => void }) {
  if (lastPage <= 1) return null;

  return (
    <div className="flex items-center justify-between border-t border-border px-5 py-3 text-sm">
      <button type="button" disabled={page <= 1} onClick={() => onChange(page - 1)} className="rounded-full px-3 py-1.5 font-medium hover:bg-surface-muted disabled:opacity-40">
        Previous
      </button>
      <span className="text-muted">
        Page {page} of {lastPage}
      </span>
      <button type="button" disabled={page >= lastPage} onClick={() => onChange(page + 1)} className="rounded-full px-3 py-1.5 font-medium hover:bg-surface-muted disabled:opacity-40">
        Next
      </button>
    </div>
  );
}

export function NoAccess() {
  return <p className="rounded-2xl bg-surface-muted px-5 py-8 text-center text-sm text-muted">You don&apos;t have permission to view this section.</p>;
}
