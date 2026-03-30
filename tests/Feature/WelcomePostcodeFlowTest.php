<?php

test('welcome page validates postcodes before redirecting into onboarding', function () {
    $component = file_get_contents(resource_path('js/pages/Welcome.vue'));

    expect($component)->not->toBeFalse();
    expect($component)->toContain("import { normalizePostcode, validatePostcode } from '@/composables/usePostcodeValidation';");
    expect($component)->toContain('postcode_verified: true');
    expect($component)->toContain('Checking postcode...');
});

test('onboarding skips the postcode step after a validated welcome-page postcode', function () {
    $component = file_get_contents(resource_path('js/pages/Onboarding/Start.vue'));

    expect($component)->not->toBeFalse();
    expect($component)->toContain('const shouldSkipPostcodeStep = computed(');
    expect($component)->toContain('currentStep.value === 1 && shouldSkipPostcodeStep.value');
    expect($component)->toContain('currentStep.value = 3;');
});
