import { EyeOff, IdCard, Users } from "lucide-react";
import Link from "next/link";
import { redirect } from "next/navigation";

import { ButtonLink } from "@/components/ui/button";
import { Logo } from "@/components/ui/logo";
import { getMe } from "@/lib/server/session";

const pillars = [
  {
    icon: Users,
    title: "Your community, simply",
    body: "A calm, chronological feed of the people you follow. Post, comment and stay close without the noise.",
  },
  {
    icon: EyeOff,
    title: "Private by default",
    body: "Appear as Ibn Zayn or Abu Hamza. Your legal name, email, phone and birthday are never shown to anyone.",
  },
  {
    icon: IdCard,
    title: "One member card",
    body: "A digital card today and an RFID/NFC card you can freeze or report lost in a tap. No money or personal data on the card.",
  },
];

const roadmap = ["Masjids", "Halal places", "Organizations", "Education", "Charity", "Shop", "Wallet"];

export default async function LandingPage() {
  if (await getMe()) redirect("/home");

  return (
    <div className="min-h-dvh">
      <header className="mx-auto flex max-w-6xl items-center justify-between px-5 py-5">
        <Logo />
        <nav className="flex items-center gap-2">
          <ButtonLink href="/login" variant="ghost">
            Sign in
          </ButtonLink>
          <ButtonLink href="/register">Join</ButtonLink>
        </nav>
      </header>

      <main>
        <section className="mx-auto max-w-6xl px-5 pb-16 pt-12 sm:pt-20">
          <p className="mb-5 inline-flex rounded-full bg-brand-soft px-3 py-1 text-sm font-medium text-brand">Assalamu alaikum</p>
          <h1 className="max-w-3xl text-4xl font-semibold leading-[1.1] tracking-tight sm:text-6xl">
            One simple app for a connected Muslim community.
          </h1>
          <p className="mt-6 max-w-xl text-lg text-muted">
            Many services, each one simple. Start with your community and your member card. Everything else grows from there.
          </p>
          <div className="mt-9 flex flex-wrap gap-3">
            <ButtonLink href="/register" size="lg">
              Create your account
            </ButtonLink>
            <ButtonLink href="/login" size="lg" variant="outline">
              I already have one
            </ButtonLink>
          </div>
        </section>

        <section className="mx-auto grid max-w-6xl gap-4 px-5 pb-16 md:grid-cols-3">
          {pillars.map(({ icon: Icon, title, body }) => (
            <article key={title} className="rounded-3xl border border-border bg-surface p-6 shadow-card">
              <div className="mb-5 flex size-11 items-center justify-center rounded-2xl bg-brand-soft text-brand">
                <Icon className="size-5" aria-hidden />
              </div>
              <h2 className="text-lg font-semibold">{title}</h2>
              <p className="mt-2 text-[15px] leading-relaxed text-muted">{body}</p>
            </article>
          ))}
        </section>

        <section className="mx-auto max-w-6xl px-5 pb-20">
          <div className="rounded-3xl border border-dashed border-border-strong px-6 py-6 sm:flex sm:items-center sm:justify-between">
            <p className="text-sm font-medium text-muted">Coming to the same account, one at a time:</p>
            <ul className="mt-3 flex flex-wrap gap-2 sm:mt-0">
              {roadmap.map((item) => (
                <li key={item} className="rounded-full bg-surface-muted px-3 py-1 text-sm text-muted">
                  {item}
                </li>
              ))}
            </ul>
          </div>
        </section>
      </main>

      <footer className="border-t border-border">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-5 py-6 text-sm text-muted">
          <span>Built with privacy and security first.</span>
          <Link href="/login" className="hover:text-text">
            Sign in
          </Link>
        </div>
      </footer>
    </div>
  );
}
