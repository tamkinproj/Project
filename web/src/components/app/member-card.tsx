import { Nfc, Snowflake, TriangleAlert } from "lucide-react";

import { cx } from "@/lib/cx";
import { APP_NAME } from "@/lib/format";
import type { MemberCard as Card } from "@/lib/types";

const overlay: Partial<Record<Card["status"], { icon: typeof Snowflake; label: string }>> = {
  frozen: { icon: Snowflake, label: "Frozen" },
  lost: { icon: TriangleAlert, label: "Reported lost" },
  revoked: { icon: TriangleAlert, label: "Revoked" },
  replaced: { icon: TriangleAlert, label: "Replaced" },
};

// Visual only. The card carries no balance and no personal data beyond what is printed.
export function MemberCardVisual({ card, holder, className }: { card: Card; holder: string; className?: string }) {
  const state = overlay[card.status];

  return (
    <div className={cx("relative aspect-[1.586] w-full overflow-hidden rounded-[22px] text-white shadow-[0_18px_40px_-12px_rgb(6_60_49/0.55)]", className)}>
      <div className={cx("absolute inset-0", card.type === "virtual" ? "bg-[linear-gradient(135deg,#0d6e5a_0%,#0a4f42_55%,#073a31_100%)]" : "bg-[linear-gradient(135deg,#17322c_0%,#0f2420_60%,#09140f_100%)]")} />
      <svg className="absolute -right-10 -top-10 size-64 opacity-[0.14]" viewBox="0 0 100 100" aria-hidden>
        <g fill="none" stroke="white" strokeWidth="0.8">
          <rect x="20" y="20" width="60" height="60" />
          <rect x="20" y="20" width="60" height="60" transform="rotate(45 50 50)" />
          <circle cx="50" cy="50" r="18" />
          <circle cx="50" cy="50" r="30" />
        </g>
      </svg>

      <div className={cx("relative flex h-full flex-col justify-between p-5 sm:p-6", state && "opacity-60 blur-[1px]")}>
        <div className="flex items-start justify-between">
          <span className="text-sm font-semibold tracking-[0.18em]">{APP_NAME.toUpperCase()}</span>
          <span className="rounded-full bg-white/15 px-2.5 py-0.5 text-[11px] font-medium uppercase tracking-wider">{card.type === "virtual" ? "Digital" : "Member"}</span>
        </div>

        <div className="flex items-center gap-3">
          <div className="h-8 w-11 rounded-md bg-[linear-gradient(135deg,#e8c871,#b48a2c)] shadow-inner" aria-hidden />
          <Nfc className="size-6 text-white/80" aria-hidden />
        </div>

        <div className="flex items-end justify-between gap-4">
          <div className="min-w-0">
            <p className="truncate text-lg font-semibold sm:text-xl">{holder}</p>
            <p className="mt-0.5 font-mono text-sm tracking-[0.2em] text-white/80" aria-label={`Card ending in ${card.last4}`}>
              •••• {card.last4}
            </p>
          </div>
          <span className="text-[11px] uppercase tracking-wider text-white/70">RFID / NFC</span>
        </div>
      </div>

      {state && (
        <div className="absolute inset-0 flex items-center justify-center bg-black/25">
          <span className="inline-flex items-center gap-2 rounded-full bg-white/90 px-4 py-2 text-sm font-semibold text-[#0b100e]">
            <state.icon className="size-4" aria-hidden /> {state.label}
          </span>
        </div>
      )}
    </div>
  );
}
