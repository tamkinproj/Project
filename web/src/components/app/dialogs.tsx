"use client";

import { useMutation } from "@tanstack/react-query";
import { useState, type FormEvent } from "react";

import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { SelectField, TextArea } from "@/components/ui/field";
import { useToast } from "@/components/ui/toast";
import { api, errorMessage } from "@/lib/api";
import { cx } from "@/lib/cx";
import type { Post, Visibility } from "@/lib/types";

const reasons = [
  ["spam", "Spam or scam"],
  ["harassment", "Harassment or bullying"],
  ["hate", "Hate speech"],
  ["violence", "Violence or threats"],
  ["sexual_content", "Sexual content"],
  ["misinformation", "False information"],
  ["impersonation", "Impersonation"],
  ["other", "Something else"],
] as const;

export function ReportDialog({ open, onClose, targetType, targetId, subject }: { open: boolean; onClose: () => void; targetType: "user" | "post" | "comment"; targetId: string; subject: string }) {
  const toast = useToast();
  const [reason, setReason] = useState<string>("");
  const [details, setDetails] = useState("");

  const report = useMutation({
    mutationFn: () => api("reports", { json: { target_type: targetType, target_id: targetId, reason, details: details || null } }),
    onSuccess: () => {
      toast.success("Thank you. Our moderators will review this.");
      setReason("");
      setDetails("");
      onClose();
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  return (
    <Dialog open={open} onClose={onClose} title={`Report ${subject}`} description="Reports are confidential. The person you report is not told who reported them.">
      <form
        onSubmit={(event: FormEvent) => {
          event.preventDefault();
          report.mutate();
        }}
        className="space-y-4"
      >
        <fieldset className="grid gap-2">
          <legend className="mb-2 text-sm font-medium">What&apos;s wrong?</legend>
          {reasons.map(([value, label]) => (
            <label key={value} className={cx("flex cursor-pointer items-center gap-3 rounded-xl border px-3.5 py-2.5 text-sm transition-colors", reason === value ? "border-brand bg-brand-soft" : "border-border hover:bg-surface-muted")}>
              <input type="radio" name="reason" value={value} checked={reason === value} onChange={() => setReason(value)} className="accent-[var(--brand)]" />
              {label}
            </label>
          ))}
        </fieldset>
        <TextArea label="Details (optional)" value={details} onChange={(e) => setDetails(e.target.value)} maxLength={1000} rows={3} />
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={!reason} loading={report.isPending}>
            Submit report
          </Button>
        </div>
      </form>
    </Dialog>
  );
}

export function ConfirmDialog({ open, onClose, onConfirm, title, description, confirmLabel, danger, pending }: { open: boolean; onClose: () => void; onConfirm: () => void; title: string; description: string; confirmLabel: string; danger?: boolean; pending?: boolean }) {
  return (
    <Dialog open={open} onClose={onClose} title={title} description={description}>
      <div className="flex justify-end gap-2">
        <Button variant="ghost" onClick={onClose}>
          Cancel
        </Button>
        <Button variant={danger ? "danger" : "primary"} onClick={onConfirm} loading={pending}>
          {confirmLabel}
        </Button>
      </div>
    </Dialog>
  );
}

export const visibilityOptions: { value: Visibility; label: string }[] = [
  { value: "public", label: "Public" },
  { value: "followers", label: "Followers" },
  { value: "only_me", label: "Only me" },
];

export function EditPostDialog({ post, open, onClose, onSaved }: { post: Post; open: boolean; onClose: () => void; onSaved: (post: Post) => void }) {
  const toast = useToast();
  const [body, setBody] = useState(post.body ?? "");
  const [visibility, setVisibility] = useState<Visibility>(post.visibility);

  const save = useMutation({
    mutationFn: () => api<{ data: Post }>(`posts/${post.id}`, { method: "PATCH", json: { body, visibility } }),
    onSuccess: ({ data }) => {
      onSaved(data);
      toast.success("Post updated.");
      onClose();
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  return (
    <Dialog open={open} onClose={onClose} title="Edit post">
      <form
        onSubmit={(event) => {
          event.preventDefault();
          save.mutate();
        }}
        className="space-y-4"
      >
        <TextArea aria-label="Post text" value={body} onChange={(e) => setBody(e.target.value)} maxLength={5000} rows={5} dir="auto" />
        <SelectField label="Who can see this" value={visibility} onChange={(e) => setVisibility(e.target.value as Visibility)}>
          {visibilityOptions.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </SelectField>
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" loading={save.isPending}>
            Save
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
