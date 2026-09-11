import { type FullConfig, request } from '@playwright/test';

/**
 * Waits until the static server answers with the manifest.
 *
 * The server is started as a container of its own right before the suite, and
 * a PHP built-in server accepts connections a moment after its process exists.
 * Waiting here keeps that race out of the first spec.
 */
export default async function globalSetup(config: FullConfig): Promise<void> {
    const baseURL = String(config.projects[0].use.baseURL);
    const context = await request.newContext({ baseURL });
    const deadline = Date.now() + 30_000;
    let lastError = '';

    try {
        while (Date.now() < deadline) {
            try {
                const response = await context.get('manifest.json');
                if (response.status() === 200) {
                    return;
                }
                lastError = `HTTP ${response.status()}`;
            } catch (error) {
                lastError = String(error);
            }
            await new Promise((resolve) => setTimeout(resolve, 500));
        }
    } finally {
        await context.dispose();
    }

    throw new Error(`The fixture server at ${baseURL} did not answer with the manifest within 30 seconds: ${lastError}`);
}
