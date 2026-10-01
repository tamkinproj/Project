"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { Lock } from "lucide-react";
import { useState, type FormEvent } from "react";

import { Button } from "@/components/ui/button";
import { TextField } from "@/components/ui/field";
import { Panel } from "@/components/ui/misc";
import { PageSpinner } from "@/components/ui/spinner";
import { useToast } from "@/components/ui/toast";
import { ApiError, api, errorMessage } from "@/lib/api";
import { useMe } from "@/lib/session";
import type { PrivateProfile } from "@/lib/types";

export function PersonalSettings() {
  const query = useQuery({ queryKey: ["private-profile"], queryFn: async () => (await api<{ data: PrivateProfile }>("me/private-profile")).data });

  if (query.isPending) return <PageSpinner />;
  if (query.isError) return <p className="text-sm text-danger">{errorMessage(query.error)}</p>;

  return <PersonalForm initial={query.data} />;
}

function PersonalForm({ initial }: { initial: PrivateProfile }) {
  const me = useMe();
  const toast = useToast();
  const [form, setForm] = useState<PrivateProfile>({
    legal_name: initial.legal_name ?? "",
    phone: initial.phone ?? "",
    date_of_birth: initial.date_of_birth ?? "",
  });

  const save = useMutation({
    mutationFn: () =>
      api("me/private-profile", {
        method: "PATCH",
        json: { legal_name: form.legal_name || null, phone: form.phone || null, date_of_birth: form.date_of_birth || null },
      }),
    onSuccess: () => toast.success("Saved privately."),
    onError: (error) => !(error instanceof ApiError && error.status === 422) && toast.error(errorMessage(error)),
  });

  const fieldError = (name: string) => (save.error instanceof ApiError ? save.error.field(name) : undefined);

  return (
    <div className="space-y-5">
      <div className="flex gap-3 rounded-2xl bg-brand-soft px-4 py-3 text-sm text-brand">
        <Lock className="mt-0.5 size-4 shrink-0" aria-hidden />
        <p>Only you can see this. It&apos;s encrypted on our servers and never appears on your profile, posts or card. All fields are optional.</p>
      </div>

      <Panel className="p-5 sm:p-6">
        <form
          onSubmit={(event: FormEvent) => {
            event.preventDefault();
            save.mutate();
          }}
          className="space-y-4"
        >
          <TextField label="Account email" value={me.email} disabled hint={me.email_verified ? "Verified" : "Not verified yet"} />
          <TextField label="Legal name" value={form.legal_name ?? ""} onChange={(e) => setForm({ ...form, legal_name: e.target.value })} maxLength={150} autoComplete="name" error={fieldError("legal_name")} />
          <TextField label="Phone" type="tel" value={form.phone ?? ""} onChange={(e) => setForm({ ...form, phone: e.target.value })} placeholder="+639171234567" autoComplete="tel" error={fieldError("phone")} />
          <TextField label="Date of birth" type="date" value={form.date_of_birth ?? ""} onChange={(e) => setForm({ ...form, date_of_birth: e.target.value })} error={fieldError("date_of_birth")} />
          <div className="flex justify-end">
            <Button type="submit" loading={save.isPending}>
              Save
            </Button>
          </div>
        </form>
      </Panel>
    </div>
  );
}
