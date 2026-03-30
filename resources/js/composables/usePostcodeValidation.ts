const postcodePattern = /^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i;

export type PostcodeValidationResult =
    | {
          valid: true;
          postcode: string;
      }
    | {
          valid: false;
          message: string;
      };

export function normalizePostcode(postcode: string): string {
    return postcode.trim().toUpperCase().replace(/\s+/g, ' ');
}

export async function validatePostcode(
    postcode: string,
): Promise<PostcodeValidationResult> {
    const normalizedPostcode = normalizePostcode(postcode);

    if (!postcodePattern.test(normalizedPostcode)) {
        return {
            valid: false,
            message: 'Enter a valid UK postcode.',
        };
    }

    try {
        const response = await fetch(
            `/start/postcode?postcode=${encodeURIComponent(normalizedPostcode)}`,
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        const payload = await response.json().catch(() => null);

        if (!response.ok) {
            return {
                valid: false,
                message:
                    payload?.errors?.postcode?.[0] ??
                    'We could not verify that postcode. Please try again.',
            };
        }

        return {
            valid: true,
            postcode: payload?.postcode ?? normalizedPostcode,
        };
    } catch {
        return {
            valid: false,
            message: 'We could not verify that postcode. Please try again.',
        };
    }
}
