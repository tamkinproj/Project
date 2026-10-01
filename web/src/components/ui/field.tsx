import { useId, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from "react";

import { cx } from "@/lib/cx";

const control =
  "w-full rounded-xl border border-border bg-surface px-3.5 text-[15px] text-text placeholder:text-muted/70 transition-colors focus:border-brand focus:outline-none focus:ring-4 focus:ring-[var(--ring)] disabled:opacity-60 aria-[invalid=true]:border-danger";

type FieldShell = { label?: ReactNode; hint?: ReactNode; error?: string; className?: string };

function Shell({ id, label, hint, error, className, children }: FieldShell & { id: string; children: ReactNode }) {
  return (
    <div className={cx("space-y-1.5", className)}>
      {label && (
        <label htmlFor={id} className="block text-sm font-medium text-text">
          {label}
        </label>
      )}
      {children}
      {error ? (
        <p id={`${id}-error`} className="text-sm text-danger" role="alert">
          {error}
        </p>
      ) : hint ? (
        <p id={`${id}-hint`} className="text-sm text-muted">
          {hint}
        </p>
      ) : null}
    </div>
  );
}

export function TextField({ label, hint, error, className, id, ...props }: FieldShell & InputHTMLAttributes<HTMLInputElement>) {
  const generated = useId();
  const fieldId = id ?? generated;

  return (
    <Shell id={fieldId} label={label} hint={hint} error={error} className={className}>
      <input
        id={fieldId}
        className={cx(control, "h-11")}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${fieldId}-error` : hint ? `${fieldId}-hint` : undefined}
        {...props}
      />
    </Shell>
  );
}

export function TextArea({ label, hint, error, className, id, ...props }: FieldShell & TextareaHTMLAttributes<HTMLTextAreaElement>) {
  const generated = useId();
  const fieldId = id ?? generated;

  return (
    <Shell id={fieldId} label={label} hint={hint} error={error} className={className}>
      <textarea
        id={fieldId}
        className={cx(control, "min-h-24 py-2.5 leading-relaxed")}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${fieldId}-error` : hint ? `${fieldId}-hint` : undefined}
        {...props}
      />
    </Shell>
  );
}

export function SelectField({ label, hint, error, className, id, children, ...props }: FieldShell & SelectHTMLAttributes<HTMLSelectElement>) {
  const generated = useId();
  const fieldId = id ?? generated;

  return (
    <Shell id={fieldId} label={label} hint={hint} error={error} className={className}>
      <select id={fieldId} className={cx(control, "h-11 pr-8")} aria-invalid={error ? true : undefined} {...props}>
        {children}
      </select>
    </Shell>
  );
}

export function Toggle({ label, description, checked, onChange, disabled }: { label: string; description?: string; checked: boolean; onChange: (value: boolean) => void; disabled?: boolean }) {
  const id = useId();

  return (
    <div className="flex items-start justify-between gap-4 py-3">
      <div>
        <label htmlFor={id} className="text-[15px] font-medium text-text">
          {label}
        </label>
        {description && <p className="mt-0.5 text-sm text-muted">{description}</p>}
      </div>
      <button
        id={id}
        type="button"
        role="switch"
        aria-checked={checked}
        disabled={disabled}
        onClick={() => onChange(!checked)}
        className={cx(
          "relative mt-0.5 inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors disabled:opacity-50",
          checked ? "bg-brand" : "bg-border-strong",
        )}
      >
        <span className={cx("inline-block size-5 rounded-full bg-white shadow transition-transform", checked ? "translate-x-6" : "translate-x-1")} />
      </button>
    </div>
  );
}
