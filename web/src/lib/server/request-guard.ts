import "server-only";

import type { NextRequest } from "next/server";

import { serverConfig } from "./config";

/**
 * CSRF defense for cookie-authenticated mutations: the request must come from this app's own
 * pages. The session cookie is also SameSite=Lax, so this is a second, independent layer.
 */
export function isSameOriginRequest(request: NextRequest): boolean {
  const origin = request.headers.get("origin");

  if (origin) {
    return origin === serverConfig.appOrigin || origin === request.nextUrl.origin;
  }

  return request.headers.get("sec-fetch-site") === "same-origin";
}
