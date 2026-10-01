"use client";

// Browser-side API client. Every call goes through this app's own /api/proxy route,
// which attaches the session from an httpOnly cookie; the browser never holds a token.

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly errors: Record<string, string[]> = {},
    public readonly code?: string,
  ) {
    super(message);
  }

  field(name: string): string | undefined {
    return this.errors[name]?.[0];
  }
}

type Options = {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  json?: unknown;
  form?: FormData;
  signal?: AbortSignal;
};

export async function api<T = unknown>(path: string, options: Options = {}): Promise<T> {
  const method = options.method ?? (options.json !== undefined || options.form ? "POST" : "GET");
  const headers: Record<string, string> = { Accept: "application/json" };
  let body: BodyInit | undefined;

  if (options.json !== undefined) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(options.json);
  } else if (options.form) {
    body = options.form;
  }

  const response = await fetch(`/api/proxy/${path.replace(/^\/+/, "")}`, {
    method,
    headers,
    body,
    credentials: "same-origin",
    signal: options.signal,
  });

  if (response.status === 401) {
    const next = encodeURIComponent(window.location.pathname + window.location.search);
    // eslint-disable-next-line @next/next/no-location-assign-relative-destination -- full load drops all cached data from the previous session
    window.location.assign(`/login?expired=1&next=${next}`);
    throw new ApiError("Your session has ended. Please sign in again.", 401);
  }

  if (response.status === 204) return undefined as T;

  const data = await response.json().catch(() => null);

  if (!response.ok) {
    const message =
      response.status === 429
        ? "You're doing that too often. Please wait a moment and try again."
        : (data?.message as string | undefined) ?? "Something went wrong. Please try again.";
    throw new ApiError(message, response.status, data?.errors ?? {}, data?.code);
  }

  return data as T;
}

export function errorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    const first = Object.values(error.errors)[0]?.[0];
    return first ?? error.message;
  }
  return "Something went wrong. Please try again.";
}
