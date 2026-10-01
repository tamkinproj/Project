import type { NextRequest } from "next/server";

import { authenticate } from "../shared";

export async function POST(request: NextRequest) {
  return authenticate(request, "auth/register", ["email", "password", "password_confirmation", "username", "display_name"]);
}
