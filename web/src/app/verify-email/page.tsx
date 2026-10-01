import { Suspense } from "react";

import { AuthShell } from "@/components/auth/auth-shell";
import { VerifyEmail } from "@/components/auth/password-forms";

export const metadata = { title: "Verify email" };

export default function VerifyEmailPage() {
  return (
    <AuthShell title="Verifying your email">
      <Suspense>
        <VerifyEmail />
      </Suspense>
    </AuthShell>
  );
}
