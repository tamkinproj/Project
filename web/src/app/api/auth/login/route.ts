import type { NextRequest } from "next/server";

import { authenticate } from "../shared";

export async function POST(request: NextRequest) {
  return authenticate(request, "auth/login", ["email", "password"]);
}
