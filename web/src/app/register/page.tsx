import Link from "next/link";
import { redirect } from "next/navigation";

import { AuthShell } from "@/components/auth/auth-shell";
import { RegisterForm } from "@/components/auth/register-form";
import { getMe } from "@/lib/server/session";

export const metadata = { title: "Create account" };

export default async function RegisterPage() {
  if (await getMe()) redirect("/home");

  return (
    <AuthShell
      title="Create your account"
      subtitle="Choose how you appear. Everything else stays private."
      footer={
        <>
          Already have an account?{" "}
          <Link href="/login" className="font-medium text-brand hover:underline">
            Sign in
          </Link>
        </>
      }
    >
      <RegisterForm />
    </AuthShell>
  );
}
