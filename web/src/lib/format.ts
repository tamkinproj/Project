const units: [Intl.RelativeTimeFormatUnit, number][] = [
  ["year", 365 * 24 * 3600],
  ["month", 30 * 24 * 3600],
  ["week", 7 * 24 * 3600],
  ["day", 24 * 3600],
  ["hour", 3600],
  ["minute", 60],
];

export function timeAgo(iso: string): string {
  const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
  if (seconds < 45) return "just now";

  const rtf = new Intl.RelativeTimeFormat("en", { numeric: "auto", style: "short" });
  for (const [unit, size] of units) {
    if (seconds >= size) return rtf.format(-Math.floor(seconds / size), unit);
  }
  return rtf.format(-Math.floor(seconds / 60), "minute");
}

export function formatDate(iso: string | null | undefined, withTime = false): string {
  if (!iso) return "—";
  return new Intl.DateTimeFormat("en", {
    dateStyle: "medium",
    ...(withTime ? { timeStyle: "short" } : {}),
  }).format(new Date(iso));
}

export function formatMonth(yearMonth: string): string {
  const [year, month] = yearMonth.split("-").map(Number);
  return new Intl.DateTimeFormat("en", { month: "long", year: "numeric" }).format(new Date(year, month - 1, 1));
}

export function humanize(value: string): string {
  const text = value.replace(/[_.]/g, " ");
  return text.charAt(0).toUpperCase() + text.slice(1);
}

export const APP_NAME = process.env.NEXT_PUBLIC_APP_NAME ?? "Ummah";
