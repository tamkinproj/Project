"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { ImagePlus, X } from "lucide-react";
import { useEffect, useRef, useState, type FormEvent } from "react";

import { Avatar } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { useToast } from "@/components/ui/toast";
import { api, errorMessage } from "@/lib/api";
import { useMe } from "@/lib/session";
import type { Post, Visibility } from "@/lib/types";

import { visibilityOptions } from "./dialogs";

const MAX_IMAGES = 4;
const MAX_BYTES = 5 * 1024 * 1024;
const TYPES = ["image/jpeg", "image/png", "image/webp"];

export function Composer() {
  const me = useMe();
  const client = useQueryClient();
  const toast = useToast();
  const fileInput = useRef<HTMLInputElement>(null);
  const [body, setBody] = useState("");
  const [files, setFiles] = useState<{ file: File; preview: string }[]>([]);
  const [visibility, setVisibility] = useState<Visibility>(me.privacy.default_post_visibility);

  const previews = useRef(new Set<string>());
  useEffect(() => {
    const urls = previews.current;
    return () => urls.forEach((url) => URL.revokeObjectURL(url));
  }, []);

  function clearFiles(predicate: (index: number) => boolean = () => true) {
    setFiles((current) =>
      current.filter((entry, index) => {
        if (!predicate(index)) return true;
        URL.revokeObjectURL(entry.preview);
        previews.current.delete(entry.preview);
        return false;
      }),
    );
  }

  const publish = useMutation({
    mutationFn: () => {
      const form = new FormData();
      if (body.trim()) form.append("body", body);
      form.append("visibility", visibility);
      files.forEach(({ file }) => form.append("images[]", file));
      return api<{ data: Post }>("posts", { form });
    },
    onSuccess: () => {
      setBody("");
      clearFiles();
      client.invalidateQueries({ queryKey: ["feed"] });
      client.invalidateQueries({ queryKey: ["profile-posts", me.profile.username] });
      toast.success("Posted.");
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  function addFiles(list: FileList | null) {
    if (!list) return;
    const accepted: { file: File; preview: string }[] = [];
    for (const file of Array.from(list)) {
      if (!TYPES.includes(file.type)) {
        toast.error(`${file.name} isn't a JPEG, PNG or WebP image.`);
      } else if (file.size > MAX_BYTES) {
        toast.error(`${file.name} is larger than 5 MB.`);
      } else {
        accepted.push({ file, preview: URL.createObjectURL(file) });
      }
    }
    const room = MAX_IMAGES - files.length;
    accepted.slice(room).forEach((entry) => URL.revokeObjectURL(entry.preview));
    accepted.slice(0, room).forEach((entry) => previews.current.add(entry.preview));
    setFiles((current) => [...current, ...accepted.slice(0, room)]);
  }

  if (!me.email_verified) {
    return (
      <div className="rounded-3xl border border-dashed border-border-strong px-5 py-6 text-center text-sm text-muted">
        Verify your email to start posting.
      </div>
    );
  }

  const canPost = (body.trim().length > 0 || files.length > 0) && body.length <= 5000;

  return (
    <form
      onSubmit={(event: FormEvent) => {
        event.preventDefault();
        if (canPost) publish.mutate();
      }}
      className="rounded-3xl border border-border bg-surface p-4 shadow-card sm:p-5"
    >
      <div className="flex gap-3">
        <Avatar name={me.profile.display_name} src={me.profile.avatar_url} />
        <textarea
          value={body}
          onChange={(e) => setBody(e.target.value)}
          placeholder="Share something with your community…"
          aria-label="Write a post"
          rows={body.split("\n").length > 2 ? 5 : 2}
          maxLength={5000}
          dir="auto"
          className="mt-2 w-full resize-none bg-transparent text-[16px] leading-relaxed placeholder:text-muted focus:outline-none"
        />
      </div>

      {files.length > 0 && (
        <ul className="mt-3 grid grid-cols-4 gap-2 sm:ml-14">
          {files.map(({ file, preview }, index) => (
            <li key={preview} className="relative">
              {/* eslint-disable-next-line @next/next/no-img-element -- local preview */}
              <img src={preview} alt={file.name} className="aspect-square w-full rounded-xl object-cover" />
              <button
                type="button"
                onClick={() => clearFiles((i) => i === index)}
                className="absolute right-1 top-1 rounded-full bg-black/60 p-1 text-white"
                aria-label={`Remove ${file.name}`}
              >
                <X className="size-3.5" />
              </button>
            </li>
          ))}
        </ul>
      )}

      <div className="mt-3 flex items-center justify-between gap-3 border-t border-border pt-3 sm:ml-14">
        <div className="flex items-center gap-1">
          <input ref={fileInput} type="file" accept={TYPES.join(",")} multiple hidden onChange={(e) => (addFiles(e.target.files), (e.target.value = ""))} />
          <button
            type="button"
            onClick={() => fileInput.current?.click()}
            disabled={files.length >= MAX_IMAGES}
            className="rounded-full p-2 text-brand transition-colors hover:bg-brand-soft disabled:opacity-40"
            aria-label="Add photos"
            title="Add photos (up to 4)"
          >
            <ImagePlus className="size-5" />
          </button>
          <select
            value={visibility}
            onChange={(e) => setVisibility(e.target.value as Visibility)}
            aria-label="Who can see this post"
            className="rounded-full border border-border bg-surface px-3 py-1.5 text-sm text-muted focus:border-brand focus:outline-none"
          >
            {visibilityOptions.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </select>
        </div>
        <div className="flex items-center gap-3">
          {body.length > 4500 && <span className="text-xs text-muted">{5000 - body.length}</span>}
          <Button type="submit" disabled={!canPost} loading={publish.isPending}>
            Post
          </Button>
        </div>
      </div>
    </form>
  );
}
