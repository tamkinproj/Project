"use client";

import { useState, type FormEvent } from "react";

import { Button } from "@/components/ui/button";
import { TextField } from "@/components/ui/field";

import { FormAlert } from "./auth-shell";

type Errors = Record<string, string[] | undefined>;

export function RegisterForm() {
  const [pending, setPending] = useState(false);
  const [errors, setErrors] = useState<Errors>({});
  const [message, setMessage] = useState<string | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setErrors({});
    setMessage(null);

    const form = Object.fromEntries(new FormData(event.currentTarget));
    const response = await fetch("/api/auth/register", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(form),
    }).catch(() => null);

    if (response?.ok) {
      // eslint-disable-next-line @next/next/no-location-assign-relative-destination -- full load drops all cached data from the previous session
      window.location.assign("/home?welcome=1");
      return;
    }

    const body = await response?.json().catch(() => null);
    setErrors(body?.errors ?? {});
    setMessage(body?.errors ? null : response?.status === 429 ? "Too many sign-ups from this network. Please try again later." : body?.message ?? "We couldn't create your account. Please try again.");
    setPending(false);
  }

  const error = (field: string) => errors[field]?.[0];

  return (
    <form onSubmit={submit} className="space-y-4" noValidate>
      {message && <FormAlert>{message}</FormAlert>}
      <TextField name="display_name" label="Display name" hint="How you appear, e.g. Ibn Zayn or Abu Hamza." autoComplete="nickname" maxLength={50} required error={error("display_name")} />
      <TextField
        name="username"
        label="Username"
        hint="Lowercase letters, numbers, dots and underscores."
        autoComplete="username"
        autoCapitalize="none"
        spellCheck={false}
        maxLength={30}
        required
        error={error("username")}
      />
      <TextField name="email" type="email" label="Email" hint="Private. Used only to sign in and keep your account safe." autoComplete="email" required error={error("email")} />
      <TextField name="password" type="password" label="Password" hint="At least 10 characters." autoComplete="new-password" required error={error("password")} />
      <TextField name="password_confirmation" type="password" label="Confirm password" autoComplete="new-password" required />
      <Button type="submit" size="lg" className="w-full" loading={pending}>
        Create account
      </Button>
      <p className="text-center text-xs text-muted">We never ask for your legal name, phone number or date of birth to join.</p>
    </form>
  );
}
