// Only same-site relative paths are allowed, so ?next= cannot become an open redirect.
export function safeNext(value: string | null | undefined, fallback = "/home"): string {
  if (!value || !value.startsWith("/") || value.startsWith("//") || value.startsWith("/\\")) return fallback;
  return value;
}
