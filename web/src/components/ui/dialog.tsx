"use client";

import { X } from "lucide-react";
import { useEffect, useRef, type ReactNode } from "react";

import { cx } from "@/lib/cx";

// Native <dialog>: focus trapping, Escape to close and inert background come from the browser.
export function Dialog({ open, onClose, title, description, children, className }: { open: boolean; onClose: () => void; title: string; description?: string; children: ReactNode; className?: string }) {
  const ref = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (open && !dialog.open) dialog.showModal();
    if (!open && dialog.open) dialog.close();
  }, [open]);

  return (
    <dialog
      ref={ref}
      onClose={onClose}
      onClick={(event) => event.target === ref.current && onClose()}
      aria-labelledby="dialog-title"
      className={cx(
        "m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl border border-border bg-surface p-0 text-text shadow-card",
        className,
      )}
    >
      {open && (
        <div className="p-6">
          <div className="mb-4 flex items-start justify-between gap-4">
            <div>
              <h2 id="dialog-title" className="text-lg font-semibold">
                {title}
              </h2>
              {description && <p className="mt-1 text-sm text-muted">{description}</p>}
            </div>
            <button type="button" onClick={onClose} className="-m-1 rounded-full p-1.5 text-muted hover:bg-surface-muted" aria-label="Close">
              <X className="size-5" />
            </button>
          </div>
          {children}
        </div>
      )}
    </dialog>
  );
}
