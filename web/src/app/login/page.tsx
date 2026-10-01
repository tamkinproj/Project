import Link from "next/link";
import { redirect } from "next/navigation";
import { Suspense } from "react";

import { AuthShell } from "@/components/auth/auth-shell";
import { LoginForm } from "@/components/auth/login-form";
import { getMe } from "@/lib/server/session";

export const metadata = { title: "Sign in" };

export default async function LoginPage() {
  if (await getMe()) redirect("/home");

  return (
    <AuthShell
      title="Welcome back"
      subtitle="Sign in to continue to your community."
      footer={
        <>
          New here?{" "}
          <Link href="/register" className="font-medium text-brand hover:underline">
            Create an account
          </Link>
        </>
      }
    >
      <Suspense>
        <LoginForm />
      </Suspense>
    </AuthShell>
  );
}
