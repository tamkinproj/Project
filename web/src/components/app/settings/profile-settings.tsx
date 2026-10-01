"use client";

import { useMutation } from "@tanstack/react-query";
import { Camera, Trash2 } from "lucide-react";
import { useRef, useState, type FormEvent } from "react";

import { Avatar } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { TextArea, TextField } from "@/components/ui/field";
import { Panel } from "@/components/ui/misc";
import { useToast } from "@/components/ui/toast";
import { ApiError, api, errorMessage } from "@/lib/api";
import { useMe, useRefreshMe } from "@/lib/session";
import type { Me } from "@/lib/types";

export function ProfileSettings() {
  const me = useMe();
  const refreshMe = useRefreshMe();
  const toast = useToast();
  const fileInput = useRef<HTMLInputElement>(null);
  const [form, setForm] = useState({
    display_name: me.profile.display_name,
    username: me.profile.username,
    bio: me.profile.bio ?? "",
    location: me.profile.location ?? "",
  });

  const save = useMutation({
    mutationFn: () => api<{ data: Me }>("me/profile", { method: "PATCH", json: { ...form, bio: form.bio || null, location: form.location || null } }),
    onSuccess: ({ data }) => {
      refreshMe(data);
      toast.success("Profile saved.");
    },
    onError: (error) => !(error instanceof ApiError && error.status === 422) && toast.error(errorMessage(error)),
  });

  const avatar = useMutation({
    mutationFn: (file: File | null) => {
      if (!file) return api<{ data: Me }>("me/avatar", { method: "DELETE" });
      const data = new FormData();
      data.append("avatar", file);
      return api<{ data: Me }>("me/avatar", { form: data });
    },
    onSuccess: ({ data }) => {
      refreshMe(data);
      toast.success("Photo updated.");
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  const fieldError = (name: string) => (save.error instanceof ApiError ? save.error.field(name) : undefined);

  return (
    <div className="space-y-5">
      <Panel className="flex items-center gap-5 p-5 sm:p-6">
        <Avatar name={me.profile.display_name} src={me.profile.avatar_url} size="xl" />
        <div>
          <p className="font-semibold">Profile photo</p>
          <p className="mt-0.5 text-sm text-muted">JPEG, PNG or WebP up to 5 MB. Location data is removed from photos.</p>
          <div className="mt-3 flex gap-2">
            <input ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp" hidden onChange={(e) => e.target.files?.[0] && avatar.mutate(e.target.files[0])} />
            <Button size="sm" icon={<Camera className="size-4" />} onClick={() => fileInput.current?.click()} loading={avatar.isPending}>
              Upload
            </Button>
            {me.profile.avatar_url && (
              <Button size="sm" variant="ghost" icon={<Trash2 className="size-4" />} onClick={() => avatar.mutate(null)}>
                Remove
              </Button>
            )}
          </div>
        </div>
      </Panel>

      <Panel className="p-5 sm:p-6">
        <form
          onSubmit={(event: FormEvent) => {
            event.preventDefault();
            save.mutate();
          }}
          className="space-y-4"
        >
          <TextField label="Display name" value={form.display_name} onChange={(e) => setForm({ ...form, display_name: e.target.value })} maxLength={50} required error={fieldError("display_name")} />
          <TextField
            label="Username"
            value={form.username}
            onChange={(e) => setForm({ ...form, username: e.target.value.toLowerCase() })}
            maxLength={30}
            autoCapitalize="none"
            spellCheck={false}
            hint="Changing it changes your profile link."
            error={fieldError("username")}
          />
          <TextArea label="Bio" value={form.bio} onChange={(e) => setForm({ ...form, bio: e.target.value })} maxLength={280} rows={3} dir="auto" hint={`${280 - form.bio.length} characters left`} error={fieldError("bio")} />
          <TextField label="Location" value={form.location} onChange={(e) => setForm({ ...form, location: e.target.value })} maxLength={80} placeholder="Optional, e.g. Cotabato City" error={fieldError("location")} />
          <div className="flex justify-end">
            <Button type="submit" loading={save.isPending}>
              Save changes
            </Button>
          </div>
        </form>
      </Panel>
    </div>
  );
}
