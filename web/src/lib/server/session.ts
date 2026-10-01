import "server-only";

import { cookies } from "next/headers";
import type { NextResponse } from "next/server";
import { cache } from "react";

import { SECURE_ORIGIN, SESSION_COOKIE, SESSION_MAX_AGE_SECONDS } from "@/lib/session-cookie";
import type { Me } from "@/lib/types";

import { backendFetch } from "./backend";

export async function getSessionToken(): Promise<string | undefined> {
  return (await cookies()).get(SESSION_COOKIE)?.value;
}

export function setSessionCookie(response: NextResponse, token: string): void {
  response.cookies.set(SESSION_COOKIE, token, {
    httpOnly: true,
    secure: SECURE_ORIGIN,
    sameSite: "lax",
    path: "/",
    maxAge: SESSION_MAX_AGE_SECONDS,
  });
}

export function clearSessionCookie(response: NextResponse): void {
  response.cookies.set(SESSION_COOKIE, "", {
    httpOnly: true,
    secure: SECURE_ORIGIN,
    sameSite: "lax",
    path: "/",
    maxAge: 0,
  });
}

// The signed-in account, verified with the API on every request (deduplicated per render).
export const getMe = cache(async (): Promise<Me | null> => {
  const token = await getSessionToken();
  if (!token) return null;

  const response = await backendFetch("me", { token });
  if (!response.ok) return null;

  const body = (await response.json()) as { data: Me };
  return body.data;
});
