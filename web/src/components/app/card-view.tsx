"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { History, Link2, RefreshCcw, ShieldCheck, Snowflake, Sun, TriangleAlert } from "lucide-react";
import { useState, type FormEvent } from "react";

import { Button } from "@/components/ui/button";
import { TextField } from "@/components/ui/field";
import { Badge, PageHeader, Panel } from "@/components/ui/misc";
import { PageSpinner } from "@/components/ui/spinner";
import { useToast } from "@/components/ui/toast";
import { api, errorMessage } from "@/lib/api";
import { formatDate, humanize } from "@/lib/format";
import { useMe } from "@/lib/session";
import type { CardEvent, CardStatus, MemberCard } from "@/lib/types";

import { ConfirmDialog } from "./dialogs";
import { MemberCardVisual } from "./member-card";

const statusTone: Record<CardStatus, "success" | "warning" | "danger" | "neutral" | "brand"> = {
  active: "success",
  frozen: "brand",
  pending_activation: "warning",
  lost: "danger",
  revoked: "danger",
  replaced: "neutral",
};

type Action = "freeze" | "unfreeze" | "report-lost" | "request-replacement";

export function CardView() {
  const me = useMe();
  const cards = useQuery({
    queryKey: ["cards"],
    queryFn: async () => (await api<{ data: MemberCard[] }>("me/cards")).data,
  });

  if (cards.isPending) return <PageSpinner />;
  if (cards.isError) return <p className="text-sm text-danger">{errorMessage(cards.error)}</p>;

  const virtual = cards.data.find((c) => c.type === "virtual");
  const physical = cards.data.filter((c) => c.type === "physical");

  return (
    <div className="space-y-6">
      <PageHeader title="My card" description="One card for every service. It identifies you; it never stores money or personal data." />

      {virtual && <CardPanel card={virtual} holder={me.profile.display_name} />}

      {physical.map((card) => (
        <CardPanel key={card.id} card={card} holder={me.profile.display_name} />
      ))}

      <LinkCardPanel />

      <Panel className="flex gap-4 p-5">
        <ShieldCheck className="mt-0.5 size-5 shrink-0 text-brand" aria-hidden />
        <div className="text-sm text-muted">
          <p className="font-medium text-text">How your card stays safe</p>
          <p className="mt-1">
            The chip carries only a credential. Every tap is checked by our servers, so a frozen, lost or revoked card stops working everywhere at once. If your card goes
            missing, report it lost — that can&apos;t be undone, and a replacement is requested automatically.
          </p>
        </div>
      </Panel>
    </div>
  );
}

function CardPanel({ card, holder }: { card: MemberCard; holder: string }) {
  const client = useQueryClient();
  const toast = useToast();
  const [confirm, setConfirm] = useState<Action | null>(null);
  const [showHistory, setShowHistory] = useState(false);

  const act = useMutation({
    mutationFn: (action: Action) => api<{ data: MemberCard }>(`me/cards/${card.id}/${action}`, { method: "POST" }),
    onSuccess: (_, action) => {
      client.invalidateQueries({ queryKey: ["cards"] });
      client.invalidateQueries({ queryKey: ["card-events", card.id] });
      client.invalidateQueries({ queryKey: ["notifications"] });
      setConfirm(null);
      toast.success(
        {
          freeze: "Card frozen. It won't work anywhere until you unfreeze it.",
          unfreeze: "Card unfrozen.",
          "report-lost": "Card reported lost and permanently disabled. A replacement has been requested.",
          "request-replacement": "Replacement requested. Our team will contact you.",
        }[action],
      );
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  const events = useQuery({
    queryKey: ["card-events", card.id],
    queryFn: async () => (await api<{ data: CardEvent[] }>(`me/cards/${card.id}/events`)).data,
    enabled: showHistory,
  });

  return (
    <Panel className="p-5 sm:p-6">
      <div className="grid gap-6 sm:grid-cols-[minmax(0,320px)_1fr] sm:items-center">
        <MemberCardVisual card={card} holder={holder} />

        <div>
          <div className="flex items-center gap-2">
            <h2 className="font-semibold">{card.type === "virtual" ? "Digital member card" : "Physical card"}</h2>
            <Badge tone={statusTone[card.status]}>{humanize(card.status)}</Badge>
          </div>
          <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            <dt className="text-muted">Card</dt>
            <dd className="font-mono">•••• {card.last4}</dd>
            <dt className="text-muted">Active since</dt>
            <dd>{formatDate(card.activated_at)}</dd>
            {card.replacement_requested_at && (
              <>
                <dt className="text-muted">Replacement</dt>
                <dd>Requested {formatDate(card.replacement_requested_at)}</dd>
              </>
            )}
          </dl>

          <div className="mt-5 flex flex-wrap gap-2">
            {card.actions.freeze && (
              <Button variant="outline" icon={<Snowflake className="size-4" />} onClick={() => setConfirm("freeze")}>
                Freeze card
              </Button>
            )}
            {card.actions.unfreeze && (
              <Button icon={<Sun className="size-4" />} onClick={() => act.mutate("unfreeze")} loading={act.isPending && act.variables === "unfreeze"}>
                Unfreeze
              </Button>
            )}
            {card.actions.report_lost && (
              <Button variant="outline" className="text-danger" icon={<TriangleAlert className="size-4" />} onClick={() => setConfirm("report-lost")}>
                Report lost
              </Button>
            )}
            {card.actions.request_replacement && (
              <Button variant="ghost" icon={<RefreshCcw className="size-4" />} onClick={() => setConfirm("request-replacement")}>
                Replace card
              </Button>
            )}
            <Button variant="ghost" icon={<History className="size-4" />} onClick={() => setShowHistory((v) => !v)} aria-expanded={showHistory}>
              Activity
            </Button>
          </div>
        </div>
      </div>

      {showHistory && (
        <div className="mt-5 border-t border-border pt-4">
          {events.isPending ? (
            <PageSpinner />
          ) : (
            <ol className="space-y-2.5">
              {events.data?.map((event) => (
                <li key={event.id} className="flex items-center justify-between text-sm">
                  <span>{humanize(event.event)}</span>
                  <time className="text-muted" dateTime={event.created_at}>
                    {formatDate(event.created_at, true)}
                  </time>
                </li>
              ))}
            </ol>
          )}
        </div>
      )}

      <ConfirmDialog
        open={confirm === "freeze"}
        onClose={() => setConfirm(null)}
        onConfirm={() => act.mutate("freeze")}
        pending={act.isPending}
        title="Freeze this card?"
        description="The card will be declined everywhere until you unfreeze it. You can unfreeze at any time."
        confirmLabel="Freeze"
      />
      <ConfirmDialog
        open={confirm === "report-lost"}
        onClose={() => setConfirm(null)}
        onConfirm={() => act.mutate("report-lost")}
        pending={act.isPending}
        title="Report this card lost?"
        description="The card is disabled permanently and can never be reactivated, even if you find it. A replacement is requested for you."
        confirmLabel="Report lost"
        danger
      />
      <ConfirmDialog
        open={confirm === "request-replacement"}
        onClose={() => setConfirm(null)}
        onConfirm={() => act.mutate("request-replacement")}
        pending={act.isPending}
        title="Request a replacement card?"
        description="Our card team will issue a new card. This card keeps working until the new one is activated."
        confirmLabel="Request replacement"
      />
    </Panel>
  );
}

function LinkCardPanel() {
  const me = useMe();
  const client = useQueryClient();
  const toast = useToast();
  const [code, setCode] = useState("");
  const [last4, setLast4] = useState("");
  const [error, setError] = useState<string | undefined>();

  const link = useMutation({
    mutationFn: () => api<{ data: MemberCard }>("me/cards/link", { json: { activation_code: code, last4 } }),
    onSuccess: ({ data }) => {
      client.invalidateQueries({ queryKey: ["cards"] });
      setCode("");
      setLast4("");
      setError(undefined);
      toast.success(`Card ending in ${data.last4} is now linked and active.`);
    },
    onError: (e) => setError(errorMessage(e)),
  });

  return (
    <Panel className="p-5 sm:p-6">
      <div className="flex items-start gap-4">
        <div className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-soft text-brand">
          <Link2 className="size-5" aria-hidden />
        </div>
        <div className="flex-1">
          <h2 className="font-semibold">Link a physical card</h2>
          <p className="mt-1 text-sm text-muted">Enter the activation code from your card mailer and the last four digits printed on the card.</p>

          {me.email_verified ? (
            <form
              onSubmit={(event: FormEvent) => {
                event.preventDefault();
                link.mutate();
              }}
              className="mt-4 grid gap-3 sm:grid-cols-[1fr_140px_auto] sm:items-start"
            >
              <TextField
                aria-label="Activation code"
                placeholder="XXXXX-XXXXX"
                value={code}
                onChange={(e) => setCode(e.target.value.toUpperCase())}
                maxLength={11}
                autoComplete="off"
                autoCapitalize="characters"
                spellCheck={false}
                className="font-mono"
                error={error}
              />
              <TextField
                aria-label="Last four digits"
                placeholder="Last 4"
                inputMode="numeric"
                value={last4}
                onChange={(e) => setLast4(e.target.value.replace(/\D/g, "").slice(0, 4))}
                autoComplete="off"
              />
              <Button type="submit" className="h-11" disabled={code.replace(/[^0-9A-Z]/g, "").length !== 10 || last4.length !== 4} loading={link.isPending}>
                Link card
              </Button>
            </form>
          ) : (
            <p className="mt-3 text-sm text-warning">Verify your email before linking a physical card.</p>
          )}
        </div>
      </div>
    </Panel>
  );
}
