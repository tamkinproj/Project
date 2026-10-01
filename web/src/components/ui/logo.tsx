import { APP_NAME } from "@/lib/format";
import { cx } from "@/lib/cx";

// An eight-pointed star built from two overlapping squares, a classic Islamic geometric motif.
export function LogoMark({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 32 32" className={cx("size-8", className)} aria-hidden>
      <rect x="6" y="6" width="20" height="20" rx="3" className="fill-brand" />
      <rect x="6" y="6" width="20" height="20" rx="3" transform="rotate(45 16 16)" className="fill-brand" />
      <circle cx="16" cy="16" r="5.5" className="fill-surface" />
      <circle cx="16" cy="16" r="2.5" className="fill-gold" />
    </svg>
  );
}

export function Logo({ className }: { className?: string }) {
  return (
    <span className={cx("inline-flex items-center gap-2.5", className)}>
      <LogoMark />
      <span className="text-lg font-semibold tracking-tight">{APP_NAME}</span>
    </span>
  );
}
