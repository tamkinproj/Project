"use client";

import { Button } from "@/components/ui/button";
import { Panel } from "@/components/ui/misc";

export default function AppError({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <Panel className="px-6 py-12 text-center">
      <h1 className="text-lg font-semibold">Something went wrong</h1>
      <p className="mt-1 text-sm text-muted">Please try again. If it keeps happening, sign out and back in.</p>
      <Button className="mt-5" onClick={reset}>
        Try again
      </Button>
    </Panel>
  );
}
