import type { Metadata, Viewport } from "next";
import { Inter, Noto_Sans_Arabic } from "next/font/google";
import { connection } from "next/server";

import { APP_NAME } from "@/lib/format";

import { Providers } from "./providers";
import "./globals.css";

const inter = Inter({ variable: "--font-inter", subsets: ["latin"], display: "swap" });
const arabic = Noto_Sans_Arabic({ variable: "--font-arabic", subsets: ["arabic"], display: "swap" });

export const metadata: Metadata = {
  title: { default: APP_NAME, template: `%s · ${APP_NAME}` },
  description: "One simple app for a connected Muslim community.",
  robots: { index: false, follow: false },
};

export const viewport: Viewport = {
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#f6f5f0" },
    { media: "(prefers-color-scheme: dark)", color: "#0b100e" },
  ],
};

export default async function RootLayout({ children }: LayoutProps<"/">) {
  // Every page renders per request so it receives a fresh CSP nonce from proxy.ts.
  await connection();

  return (
    <html lang="en" className={`${inter.variable} ${arabic.variable}`}>
      <body className="min-h-dvh font-sans antialiased">
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
