import { cx } from "@/lib/cx";

const sizes = { xs: "size-7 text-[11px]", sm: "size-9 text-xs", md: "size-11 text-sm", lg: "size-16 text-lg", xl: "size-24 text-2xl" };

export function Avatar({ name, src, size = "md", className }: { name: string; src?: string | null; size?: keyof typeof sizes; className?: string }) {
  const initials = name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");

  return (
    <span className={cx("relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-soft font-semibold text-brand ring-1 ring-black/5", sizes[size], className)}>
      {src ? (
        // eslint-disable-next-line @next/next/no-img-element -- signed, short-lived media URLs from the API
        <img src={src} alt="" className="size-full object-cover" referrerPolicy="no-referrer" />
      ) : (
        <span aria-hidden>{initials || "?"}</span>
      )}
    </span>
  );
}
