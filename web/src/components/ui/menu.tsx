"use client";

import { MoreHorizontal } from "lucide-react";
import { useEffect, useRef, useState, type ReactNode } from "react";

import { cx } from "@/lib/cx";

export type MenuItem = { label: string; icon?: ReactNode; onSelect: () => void; danger?: boolean };

export function Menu({ items, label = "More options" }: { items: MenuItem[]; label?: string }) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const close = (event: MouseEvent | KeyboardEvent) => {
      if (event instanceof KeyboardEvent ? event.key === "Escape" : !ref.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener("mousedown", close);
    document.addEventListener("keydown", close);
    return () => {
      document.removeEventListener("mousedown", close);
      document.removeEventListener("keydown", close);
    };
  }, [open]);

  if (items.length === 0) return null;

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        aria-label={label}
        aria-haspopup="menu"
        aria-expanded={open}
        onClick={() => setOpen((v) => !v)}
        className="rounded-full p-2 text-muted transition-colors hover:bg-surface-muted hover:text-text"
      >
        <MoreHorizontal className="size-5" />
      </button>
      {open && (
        <div role="menu" className="absolute right-0 z-30 mt-1 min-w-48 overflow-hidden rounded-2xl border border-border bg-surface py-1.5 shadow-card">
          {items.map((item) => (
            <button
              key={item.label}
              type="button"
              role="menuitem"
              onClick={() => {
                setOpen(false);
                item.onSelect();
              }}
              className={cx("flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm hover:bg-surface-muted", item.danger ? "text-danger" : "text-text")}
            >
              {item.icon}
              {item.label}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
