import type { ReactNode } from "react";

import { cx } from "@/lib/cx";

export function Panel({ className, children }: { className?: string; children: ReactNode }) {
  return <section className={cx("rounded-3xl border border-border bg-surface shadow-card", className)}>{children}</section>;
}

const tones = {
  neutral: "bg-surface-muted text-muted",
  brand: "bg-brand-soft text-brand",
  success: "bg-success-soft text-success",
  warning: "bg-warning-soft text-warning",
  danger: "bg-danger-soft text-danger",
};

export function Badge({ tone = "neutral", children, className }: { tone?: keyof typeof tones; children: ReactNode; className?: string }) {
  return <span className={cx("inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium", tones[tone], className)}>{children}</span>;
}

export function EmptyState({ icon, title, children }: { icon: ReactNode; title: string; children?: ReactNode }) {
  return (
    <div className="flex flex-col items-center px-6 py-14 text-center">
      <div className="mb-4 flex size-14 items-center justify-center rounded-2xl bg-brand-soft text-brand">{icon}</div>
      <h3 className="text-base font-semibold">{title}</h3>
      {children && <div className="mt-1.5 max-w-sm text-sm text-muted">{children}</div>}
    </div>
  );
}

export function Segmented<T extends string>({ value, onChange, options, label }: { value: T; onChange: (value: T) => void; options: { value: T; label: string }[]; label: string }) {
  return (
    <div role="tablist" aria-label={label} className="inline-flex rounded-full bg-surface-muted p-1">
      {options.map((option) => (
        <button
          key={option.value}
          type="button"
          role="tab"
          aria-selected={value === option.value}
          onClick={() => onChange(option.value)}
          className={cx(
            "rounded-full px-4 py-1.5 text-sm font-medium transition-colors",
            value === option.value ? "bg-surface text-text shadow-sm" : "text-muted hover:text-text",
          )}
        >
          {option.label}
        </button>
      ))}
    </div>
  );
}

export function PageHeader({ title, description, actions }: { title: string; description?: ReactNode; actions?: ReactNode }) {
  return (
    <header className="mb-5 flex items-end justify-between gap-4">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
        {description && <p className="mt-1 text-sm text-muted">{description}</p>}
      </div>
      {actions}
    </header>
  );
}
