import "server-only";

export const serverConfig = {
  apiUrl: (process.env.API_URL ?? "http://127.0.0.1:8000").replace(/\/+$/, ""),
  appOrigin: (process.env.APP_ORIGIN ?? "http://localhost:3000").replace(/\/+$/, ""),
  trustedProxyHops: Math.max(0, Number.parseInt(process.env.TRUSTED_PROXY_HOPS ?? "0", 10) || 0),
};
