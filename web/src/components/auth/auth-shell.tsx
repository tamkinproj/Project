import Link from "next/link";
import type { ReactNode } from "react";

import { Logo } from "@/components/ui/logo";

function Pattern() {
  return (
    <svg className="absolute inset-0 size-full opacity-[0.12]" aria-hidden>
      <defs>
        <pattern id="girih" width="64" height="64" patternUnits="userSpaceOnUse">
          <g fill="none" stroke="currentColor" strokeWidth="1.2">
            <rect x="16" y="16" width="32" height="32" />
            <rect x="16" y="16" width="32" height="32" transform="rotate(45 32 32)" />
            <circle cx="32" cy="32" r="8" />
            <path d="M0 0L16 16M64 0L48 16M0 64L16 48M64 64L48 48" />
          </g>
        </pattern>
      </defs>
      <rect width="100%" height="100%" fill="url(#girih)" />
    </svg>
  );
}

export function AuthShell({ title, subtitle, children, footer }: { title: string; subtitle?: ReactNode; children: ReactNode; footer?: ReactNode }) {
  return (
    <div className="grid min-h-dvh lg:grid-cols-[1fr_minmax(480px,560px)]">
      <aside className="relative hidden overflow-hidden bg-brand text-brand-fg lg:flex lg:flex-col lg:justify-between lg:p-12">
        <Pattern />
        <Link href="/" className="relative text-brand-fg [&_rect]:fill-brand-fg [&_.fill-surface]:fill-brand">
          <Logo />
        </Link>
        <div className="relative max-w-md">
          <p className="text-3xl font-semibold leading-tight tracking-tight">One simple app for a connected Muslim community.</p>
          <p className="mt-4 text-brand-fg/80">
            Share with the people you care about, find your community, and carry one member card for every service.
          </p>
        </div>
        <p className="relative text-sm text-brand-fg/70">Your legal name, email and phone are never shown publicly.</p>
      </aside>

      <main className="flex flex-col px-5 py-8 sm:px-10">
        <Link href="/" className="mb-10 lg:hidden">
          <Logo />
        </Link>
        <div className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center">
          <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
          {subtitle && <p className="mt-2 text-[15px] text-muted">{subtitle}</p>}
          <div className="mt-8">{children}</div>
          {footer && <div className="mt-8 text-center text-sm text-muted">{footer}</div>}
        </div>
      </main>
    </div>
  );
}

export function FormAlert({ tone = "danger", children }: { tone?: "danger" | "success" | "info"; children: ReactNode }) {
  const styles = {
    danger: "bg-danger-soft text-danger",
    success: "bg-success-soft text-success",
    info: "bg-brand-soft text-brand",
  };

  return (
    <div role={tone === "danger" ? "alert" : "status"} className={`mb-5 rounded-2xl px-4 py-3 text-sm ${styles[tone]}`}>
      {children}
    </div>
  );
}
