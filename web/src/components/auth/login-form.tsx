"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useState, type FormEvent } from "react";

import { Button } from "@/components/ui/button";
import { TextField } from "@/components/ui/field";
import { safeNext } from "@/lib/safe-redirect";

import { FormAlert } from "./auth-shell";

export function LoginForm() {
  const params = useSearchParams();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setError(null);

    const form = new FormData(event.currentTarget);
    const response = await fetch("/api/auth/login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email: form.get("email"), password: form.get("password") }),
    }).catch(() => null);

    if (response?.ok) {
      // Full page load, so no cached data from a previous session survives.
      window.location.assign(safeNext(params.get("next")));
      return;
    }

    const body = await response?.json().catch(() => null);
    setError(
      response?.status === 429 && !body?.errors
        ? "Too many attempts. Please wait a minute and try again."
        : body?.errors?.email?.[0] ?? body?.message ?? "We couldn't sign you in. Please try again.",
    );
    setPending(false);
  }

  return (
    <form onSubmit={submit} className="space-y-4" noValidate>
      {params.get("expired") && !error && <FormAlert tone="info">Your session ended. Please sign in again.</FormAlert>}
      {params.get("reset") && !error && <FormAlert tone="success">Password updated. Sign in with your new password.</FormAlert>}
      {error && <FormAlert>{error}</FormAlert>}
      <TextField name="email" type="email" label="Email" autoComplete="email" required autoFocus />
      <TextField name="password" type="password" label="Password" autoComplete="current-password" required />
      <div className="flex justify-end">
        <Link href="/forgot-password" className="text-sm font-medium text-brand hover:underline">
          Forgot password?
        </Link>
      </div>
      <Button type="submit" size="lg" className="w-full" loading={pending}>
        Sign in
      </Button>
    </form>
  );
}
