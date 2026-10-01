"use client";

import { useMutation } from "@tanstack/react-query";
import { useState, type FormEvent } from "react";

import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { TextField } from "@/components/ui/field";
import { Panel } from "@/components/ui/misc";
import { ApiError, api, errorMessage } from "@/lib/api";
import { signOut } from "@/lib/session";

export function AccountSettings() {
  const [dialog, setDialog] = useState<"deactivate" | "delete" | null>(null);

  return (
    <div className="space-y-5">
      <Panel className="p-5 sm:p-6">
        <h2 className="font-semibold">Sign out</h2>
        <p className="mt-1 text-sm text-muted">Sign out of this device.</p>
        <Button variant="outline" className="mt-4" onClick={signOut}>
          Sign out
        </Button>
      </Panel>

      <Panel className="p-5 sm:p-6">
        <h2 className="font-semibold">Deactivate account</h2>
        <p className="mt-1 text-sm text-muted">Hide your profile and posts and sign out everywhere. Sign in again any time to reactivate.</p>
        <Button variant="outline" className="mt-4" onClick={() => setDialog("deactivate")}>
          Deactivate
        </Button>
      </Panel>

      <Panel className="border-danger/30 p-5 sm:p-6">
        <h2 className="font-semibold text-danger">Delete account permanently</h2>
        <p className="mt-1 text-sm text-muted">
          Your profile, posts, photos, comments and personal information are erased, and your cards are revoked. This cannot be undone.
        </p>
        <Button variant="danger" className="mt-4" onClick={() => setDialog("delete")}>
          Delete account
        </Button>
      </Panel>

      {dialog && <ConfirmWithPassword mode={dialog} onClose={() => setDialog(null)} />}
    </div>
  );
}

function ConfirmWithPassword({ mode, onClose }: { mode: "deactivate" | "delete"; onClose: () => void }) {
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");

  const submit = useMutation({
    mutationFn: () =>
      mode === "delete"
        ? api("me", { method: "DELETE", json: { password, confirmation } })
        : api("me/deactivate", { method: "POST", json: { password } }),
    onSuccess: () => signOut(),
  });

  const error = submit.error instanceof ApiError ? (submit.error.field("password") ?? submit.error.field("confirmation") ?? submit.error.message) : submit.error ? errorMessage(submit.error) : undefined;

  return (
    <Dialog
      open
      onClose={onClose}
      title={mode === "delete" ? "Delete your account?" : "Deactivate your account?"}
      description={mode === "delete" ? "Enter your password and type DELETE to confirm. This is permanent." : "Enter your password to confirm."}
    >
      <form
        onSubmit={(event: FormEvent) => {
          event.preventDefault();
          submit.mutate();
        }}
        className="space-y-4"
      >
        <TextField label="Password" type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} error={error} autoFocus />
        {mode === "delete" && <TextField label="Type DELETE" value={confirmation} onChange={(e) => setConfirmation(e.target.value)} autoComplete="off" />}
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" variant="danger" loading={submit.isPending} disabled={!password || (mode === "delete" && confirmation !== "DELETE")}>
            {mode === "delete" ? "Delete forever" : "Deactivate"}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
