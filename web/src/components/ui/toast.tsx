"use client";

import { CheckCircle2, CircleAlert } from "lucide-react";
import { createContext, useCallback, useContext, useState, type ReactNode } from "react";

import { cx } from "@/lib/cx";

type Toast = { id: number; message: string; tone: "success" | "error" };
type ToastApi = { success: (message: string) => void; error: (message: string) => void };

const ToastContext = createContext<ToastApi | null>(null);

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);

  const push = useCallback((message: string, tone: Toast["tone"]) => {
    const id = Date.now() + Math.random();
    setToasts((current) => [...current.slice(-2), { id, message, tone }]);
    setTimeout(() => setToasts((current) => current.filter((t) => t.id !== id)), 4500);
  }, []);

  const [api] = useState<ToastApi>(() => ({
    success: (message) => push(message, "success"),
    error: (message) => push(message, "error"),
  }));

  return (
    <ToastContext.Provider value={api}>
      {children}
      <div aria-live="polite" className="pointer-events-none fixed inset-x-0 bottom-20 z-50 flex flex-col items-center gap-2 px-4 lg:bottom-6">
        {toasts.map((toast) => (
          <div
            key={toast.id}
            role={toast.tone === "error" ? "alert" : "status"}
            className={cx(
              "pointer-events-auto flex max-w-md items-center gap-2.5 rounded-2xl px-4 py-3 text-sm font-medium shadow-card",
              toast.tone === "success" ? "bg-text text-bg" : "bg-danger text-white",
            )}
          >
            {toast.tone === "success" ? <CheckCircle2 className="size-4 shrink-0" /> : <CircleAlert className="size-4 shrink-0" />}
            {toast.message}
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
}

export function useToast(): ToastApi {
  const context = useContext(ToastContext);
  if (!context) throw new Error("useToast must be used inside ToastProvider");
  return context;
}
