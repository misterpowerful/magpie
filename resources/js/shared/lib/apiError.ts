import { isAxiosError } from 'axios'

/**
 * Shape of the backend's unified JSON error envelope (ApiError/
 * ApiErrorRenderer on the Laravel side) — correlationId/code are always
 * present; errors is only present for validation failures.
 */
interface ApiErrorBody {
    correlationId?: string
    code?: string
    message?: string
    errors?: Record<string, string[]>
}

function bodyOf(error: unknown): ApiErrorBody | undefined {
    return isAxiosError(error) ? (error.response?.data as ApiErrorBody | undefined) : undefined
}

export function apiErrorMessage(error: unknown, fallback = 'Something went wrong.'): string {
    return bodyOf(error)?.message ?? fallback
}

/**
 * A correlation id to show the user ("Reference: <id>") on any
 * non-validation error, so a support request can be tied back to a
 * server-side log line.
 */
export function errorReference(error: unknown): string | null {
    return bodyOf(error)?.correlationId ?? null
}

export function validationErrors(error: unknown): Record<string, string[]> {
    return bodyOf(error)?.errors ?? {}
}
