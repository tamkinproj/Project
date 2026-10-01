import "server-only";

import { serverConfig } from "./config";

type BackendInit = {
  method?: string;
  token?: string;
  body?: BodyInit | null;
  contentType?: string | null;
  incoming?: Headers;
};

/**
 * The client's IP as seen by the outermost trusted proxy. Each trusted hop appends the
 * address it received the connection from, so entries to the left of that are client-supplied
 * and untrusted.
 */
export function clientIp(headers: Headers): string | null {
  const chain = (headers.get("x-forwarded-for") ?? "")
    .split(",")
    .map((part) => part.trim())
    .filter(Boolean);

  if (chain.length === 0) return null;

  const index = Math.max(0, chain.length - Math.max(1, serverConfig.trustedProxyHops));
  const ip = chain[index];

  return /^[0-9a-fA-F:.]{2,45}$/.test(ip) ? ip : null;
}

// Calls the Laravel API from the server. Browsers never talk to the API directly.
export async function backendFetch(path: string, init: BackendInit = {}): Promise<Response> {
  const headers = new Headers({ Accept: "application/json" });

  if (init.contentType) headers.set("Content-Type", init.contentType);
  if (init.token) headers.set("Authorization", `Bearer ${init.token}`);

  if (init.incoming) {
    const userAgent = init.incoming.get("user-agent");
    const ip = clientIp(init.incoming);
    if (userAgent) headers.set("User-Agent", userAgent.slice(0, 512));
    if (ip) headers.set("X-Forwarded-For", ip);
  }

  headers.set("X-Request-Id", crypto.randomUUID());

  return fetch(`${serverConfig.apiUrl}/api/v1/${path.replace(/^\/+/, "")}`, {
    method: init.method ?? "GET",
    headers,
    body: init.body ?? null,
    cache: "no-store",
    redirect: "manual",
  });
}
