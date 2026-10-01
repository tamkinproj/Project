import { NextResponse, type NextRequest } from "next/server";

import { backendFetch } from "@/lib/server/backend";
import { isSameOriginRequest } from "@/lib/server/request-guard";
import { clearSessionCookie, getSessionToken } from "@/lib/server/session";

export async function POST(request: NextRequest) {
  if (!isSameOriginRequest(request)) {
    return NextResponse.json({ message: "Cross-origin request rejected." }, { status: 403 });
  }

  const token = await getSessionToken();

  if (token) {
    // Best effort: the cookie is cleared even if the API is unreachable or the token already revoked.
    await backendFetch("auth/logout", { method: "POST", token, incoming: request.headers }).catch(() => null);
  }

  const response = new NextResponse(null, { status: 204 });
  clearSessionCookie(response);

  return response;
}
