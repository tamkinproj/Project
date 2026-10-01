"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useEffect, useRef, useState, type FormEvent } from "react";

import { Button, ButtonLink } from "@/components/ui/button";
import { TextField } from "@/components/ui/field";
import { Spinner } from "@/components/ui/spinner";

import { FormAlert } from "./auth-shell";

async function post(path: string, payload: unknown) {
  const response = await fetch(`/api/proxy/${path}`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  }).catch(() => null);
  const body = await response?.json().catch(() => null);
  return { ok: !!response?.ok, status: response?.status ?? 0, body };
}

export function ForgotPasswordForm() {
  const [pending, setPending] = useState(false);
  const [sent, setSent] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setError(null);
    const { ok, status, body } = await post("auth/forgot-password", { email: new FormData(event.currentTarget).get("email") });
    if (ok) setSent(body.message);
    else setError(status === 429 ? "Too many requests. Please try again in a few minutes." : body?.errors?.email?.[0] ?? body?.message ?? "Please try again.");
    setPending(false);
  }

  if (sent) {
    return (
      <>
        <FormAlert tone="success">{sent}</FormAlert>
        <ButtonLink href="/login" variant="outline" className="w-full">
          Back to sign in
        </ButtonLink>
      </>
    );
  }

  return (
    <form onSubmit={submit} className="space-y-4" noValidate>
      {error && <FormAlert>{error}</FormAlert>}
      <TextField name="email" type="email" label="Email" autoComplete="email" required autoFocus />
      <Button type="submit" size="lg" className="w-full" loading={pending}>
        Send reset link
      </Button>
    </form>
  );
}

export function ResetPasswordForm() {
  const params = useSearchParams();
  const [pending, setPending] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<string | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setErrors({});
    setMessage(null);
    const form = new FormData(event.currentTarget);
    const { ok, body } = await post("auth/reset-password", {
      token: params.get("token"),
      email: params.get("email"),
      password: form.get("password"),
      password_confirmation: form.get("password_confirmation"),
    });
    if (ok) {
      // eslint-disable-next-line @next/next/no-location-assign-relative-destination -- full load drops all cached data from the previous session
      window.location.assign("/login?reset=1");
      return;
    }
    setErrors(body?.errors ?? {});
    setMessage(body?.errors?.email?.[0] ?? (body?.errors ? null : body?.message ?? "Please try again."));
    setPending(false);
  }

  if (!params.get("token") || !params.get("email")) {
    return (
      <>
        <FormAlert>This reset link is incomplete. Request a new one.</FormAlert>
        <ButtonLink href="/forgot-password" className="w-full">
          Request a new link
        </ButtonLink>
      </>
    );
  }

  return (
    <form onSubmit={submit} className="space-y-4" noValidate>
      {message && <FormAlert>{message}</FormAlert>}
      <TextField name="password" type="password" label="New password" hint="At least 10 characters." autoComplete="new-password" required error={errors.password?.[0]} />
      <TextField name="password_confirmation" type="password" label="Confirm new password" autoComplete="new-password" required />
      <Button type="submit" size="lg" className="w-full" loading={pending}>
        Set new password
      </Button>
      <p className="text-center text-xs text-muted">For your security, every device will be signed out.</p>
    </form>
  );
}

export function VerifyEmail() {
  const params = useSearchParams();
  const id = params.get("id") ?? "";
  const hash = params.get("hash") ?? "";
  const wellFormed = /^[0-9a-z]{26}$/.test(id) && /^[0-9a-f]{40}$/.test(hash);
  const [result, setResult] = useState<"done" | "failed" | null>(null);
  const started = useRef(false);

  useEffect(() => {
    if (started.current || !wellFormed) return;
    started.current = true;

    const query = new URLSearchParams({ expires: params.get("expires") ?? "", signature: params.get("signature") ?? "" });
    fetch(`/api/proxy/auth/email/verify/${id}/${hash}?${query}`, { headers: { Accept: "application/json" } })
      .then((response) => setResult(response.ok ? "done" : "failed"))
      .catch(() => setResult("failed"));
  }, [id, hash, params, wellFormed]);

  const state = !wellFormed ? "failed" : (result ?? "working");

  if (state === "working") {
    return (
      <div className="flex items-center gap-3 text-muted">
        <Spinner /> Confirming your email…
      </div>
    );
  }

  if (state === "failed") {
    return (
      <>
        <FormAlert>This link is invalid or has expired. Sign in and request a new verification email from the banner at the top of the app.</FormAlert>
        <ButtonLink href="/home" className="w-full">
          Continue
        </ButtonLink>
      </>
    );
  }

  return (
    <>
      <FormAlert tone="success">Your email is verified. You can now post, comment and connect.</FormAlert>
      <ButtonLink href="/home" className="w-full">
        Go to your feed
      </ButtonLink>
      <p className="mt-4 text-center text-sm text-muted">
        Not signed in on this device?{" "}
        <Link href="/login" className="font-medium text-brand hover:underline">
          Sign in
        </Link>
      </p>
    </>
  );
}
