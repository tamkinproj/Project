import { NextResponse, type NextRequest } from "next/server";

import { backendFetch } from "@/lib/server/backend";
import { isSameOriginRequest } from "@/lib/server/request-guard";
import { clearSessionCookie, getSessionToken } from "@/lib/server/session";

const MAX_BODY_BYTES = 25 * 1024 * 1024;
const FORWARDED_RESPONSE_HEADERS = ["content-type", "x-request-id", "retry-after", "x-ratelimit-limit", "x-ratelimit-remaining"];
// These must go through the dedicated /api/auth/* handlers, which manage the session cookie.
const BLOCKED_PATHS = new Set(["auth/login", "auth/register", "auth/logout"]);

async function forward(request: NextRequest, context: RouteContext<"/api/proxy/[...path]">) {
  const { path } = await context.params;
  const joined = path.map((segment) => encodeURIComponent(segment)).join("/");

  if (BLOCKED_PATHS.has(joined) || path.some((segment) => segment === ".." || segment === ".")) {
    return NextResponse.json({ message: "Not found." }, { status: 404 });
  }

  const method = request.method.toUpperCase();
  const mutating = method !== "GET" && method !== "HEAD";

  if (mutating && !isSameOriginRequest(request)) {
    return NextResponse.json({ message: "Cross-origin request rejected." }, { status: 403 });
  }

  const declaredLength = Number(request.headers.get("content-length") ?? 0);
  if (declaredLength > MAX_BODY_BYTES) {
    return NextResponse.json({ message: "The upload is too large." }, { status: 413 });
  }

  const body = mutating ? await request.arrayBuffer() : null;
  if (body && body.byteLength > MAX_BODY_BYTES) {
    return NextResponse.json({ message: "The upload is too large." }, { status: 413 });
  }

  let upstream: Response;
  try {
    upstream = await backendFetch(joined + request.nextUrl.search, {
      method,
      token: await getSessionToken(),
      body: body && body.byteLength > 0 ? body : null,
      contentType: request.headers.get("content-type"),
      incoming: request.headers,
    });
  } catch {
    return NextResponse.json({ message: "The service is temporarily unavailable." }, { status: 503 });
  }

  const headers = new Headers({ "Cache-Control": "no-store" });
  for (const name of FORWARDED_RESPONSE_HEADERS) {
    const value = upstream.headers.get(name);
    if (value) headers.set(name, value);
  }

  const response = new NextResponse(upstream.status === 204 ? null : upstream.body, { status: upstream.status, headers });

  // An expired or revoked token ends the browser session too.
  if (upstream.status === 401) clearSessionCookie(response);

  return response;
}

export { forward as GET, forward as POST, forward as PUT, forward as PATCH, forward as DELETE };
