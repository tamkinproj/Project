import { ButtonLink } from "@/components/ui/button";
import { LogoMark } from "@/components/ui/logo";

export default function NotFound() {
  return (
    <main className="flex min-h-dvh flex-col items-center justify-center px-6 text-center">
      <LogoMark className="size-12" />
      <h1 className="mt-6 text-2xl font-semibold tracking-tight">Page not found</h1>
      <p className="mt-2 max-w-sm text-muted">The page doesn&apos;t exist, or you don&apos;t have access to it.</p>
      <ButtonLink href="/home" className="mt-6">
        Go home
      </ButtonLink>
    </main>
  );
}
