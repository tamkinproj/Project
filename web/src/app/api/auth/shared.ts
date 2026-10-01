import "server-only";

import { NextResponse, type NextRequest } from "next/server";

import { backendFetch } from "@/lib/server/backend";
import { isSameOriginRequest } from "@/lib/server/request-guard";
import { setSessionCookie } from "@/lib/server/session";

// Exchanges credentials for an API token and keeps the token in an httpOnly cookie.
// The token itself is never sent to browser JavaScript.
export async function authenticate(request: NextRequest, path: string, fields: string[]) {
  if (!isSameOriginRequest(request)) {
    return NextResponse.json({ message: "Cross-origin request rejected." }, { status: 403 });
  }

  const input = (await request.json().catch(() => null)) as Record<string, unknown> | null;
  if (!input || typeof input !== "object") {
    return NextResponse.json({ message: "Invalid request." }, { status: 400 });
  }

  const payload = Object.fromEntries(fields.map((field) => [field, input[field]]));

  const upstream = await backendFetch(path, {
    method: "POST",
    body: JSON.stringify(payload),
    contentType: "application/json",
    incoming: request.headers,
  });

  const body = (await upstream.json().catch(() => ({ message: "The service is unavailable." }))) as {
    token?: string;
    user?: unknown;
  };

  if (!upstream.ok || !body.token) {
    return NextResponse.json(body, { status: upstream.ok ? 502 : upstream.status });
  }

  const response = NextResponse.json({ user: body.user }, { status: upstream.status });
  setSessionCookie(response, body.token);

  return response;
}
