// Over HTTPS the cookie is Secure and uses the __Host- prefix, which makes browsers enforce
// Secure, Path=/ and no Domain attribute. Plain HTTP is only for local development.
export const SECURE_ORIGIN = (process.env.APP_ORIGIN ?? "").startsWith("https://");

export const SESSION_COOKIE = SECURE_ORIGIN ? "__Host-session" : "session";

// Matches the API's token lifetime (AUTH_TOKEN_TTL_MINUTES); the API remains the authority.
export const SESSION_MAX_AGE_SECONDS = 60 * 60 * 24 * 30;
