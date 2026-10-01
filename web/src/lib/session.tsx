"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { createContext, useContext, type ReactNode } from "react";

import { api } from "@/lib/api";
import type { Me, Permission } from "@/lib/types";

const SessionContext = createContext<Me | null>(null);

// Seeded from the server-rendered layout, then kept fresh on the client.
export function SessionProvider({ initial, children }: { initial: Me; children: ReactNode }) {
  const { data } = useQuery({
    queryKey: ["me"],
    queryFn: async () => (await api<{ data: Me }>("me")).data,
    initialData: initial,
  });

  return <SessionContext.Provider value={data}>{children}</SessionContext.Provider>;
}

export function useMe(): Me {
  const me = useContext(SessionContext);
  if (!me) throw new Error("useMe must be used inside SessionProvider");
  return me;
}

export function useCan(permission: Permission): boolean {
  return useMe().permissions.includes(permission);
}

export function useRefreshMe() {
  const client = useQueryClient();
  return (me?: Me) => (me ? client.setQueryData(["me"], me) : client.invalidateQueries({ queryKey: ["me"] }));
}

export async function signOut() {
  await fetch("/api/auth/logout", { method: "POST", credentials: "same-origin" });
  // eslint-disable-next-line @next/next/no-location-assign-relative-destination -- full load drops all cached data from the previous session
  window.location.assign("/login");
}
