import { redirect } from "next/navigation";

import { AppShell } from "@/components/app/app-shell";
import { getMe } from "@/lib/server/session";
import { SessionProvider } from "@/lib/session";

export default async function AppLayout({ children }: LayoutProps<"/">) {
  const me = await getMe();

  // A cookie can exist but be expired or revoked; the API is the authority.
  if (!me) redirect("/login?expired=1");

  return (
    <SessionProvider initial={me}>
      <AppShell>{children}</AppShell>
    </SessionProvider>
  );
}
