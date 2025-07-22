<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Physical Attributes
            'height_cm' => ['sometimes', 'integer', 'min:120', 'max:250'],
            'weight_kg' => ['sometimes', 'numeric', 'min:30', 'max:300'],
            'body_type' => ['sometimes', 'string', 'in:slim,average,athletic,heavy'],
            'complexion' => ['sometimes', 'string', 'in:very_fair,fair,wheatish,brown,dark'],
            'blood_group' => ['sometimes', 'string', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'physically_challenged' => ['sometimes', 'boolean'],
            'physical_challenge_details' => ['required_if:physically_challenged,true', 'string', 'max:500'],

            // Location
            'current_city' => ['sometimes', 'string', 'max:100'],
            'current_state' => ['sometimes', 'string', 'max:100'],
            'current_country' => ['sometimes', 'string', 'max:100'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],

            // Education & Career
            'education_level' => ['sometimes', 'string', 'in:high_school,diploma,bachelors,masters,phd,other'],
            'education_details' => ['sometimes', 'string', 'max:255'],
            'college_university' => ['sometimes', 'string', 'max:255'],
            'occupation' => ['sometimes', 'string', 'max:100'],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'job_title' => ['sometimes', 'string', 'max:100'],
            'annual_income' => ['sometimes', 'numeric', 'min:0'],
            'income_currency' => ['sometimes', 'string', 'size:3'],
            'work_location' => ['sometimes', 'string', 'max:100'],

            // Cultural & Religious
            'religion' => ['sometimes', 'string', 'in:buddhist,christian,hindu,islam,catholic,other'],
            'caste' => ['sometimes', 'string', 'max:100'],
            'subcaste' => ['sometimes', 'string', 'max:100'],
            'mother_tongue' => ['sometimes', 'string', 'max:50'],
            'other_languages' => ['sometimes', 'array'],
            'other_languages.*' => ['string', 'max:50'],

            // Family Information
            'father_occupation' => ['sometimes', 'string', 'max:100'],
            'mother_occupation' => ['sometimes', 'string', 'max:100'],
            'siblings_count' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'family_type' => ['sometimes', 'string', 'in:nuclear,joint'],
            'family_values' => ['sometimes', 'string', 'in:traditional,modern,moderate'],
            'family_location' => ['sometimes', 'string', 'max:100'],
            'family_income' => ['sometimes', 'string', 'in:low,middle,upper_middle,high'],

            // Lifestyle
            'dietary_preferences' => ['sometimes', 'string', 'in:vegetarian,non_vegetarian,vegan,jain,kosher,halal'],
            'smoking_habits' => ['sometimes', 'string', 'in:never,occasionally,regularly,trying_to_quit'],
            'drinking_habits' => ['sometimes', 'string', 'in:never,socially,occasionally,regularly'],
            'exercise_habits' => ['sometimes', 'string', 'in:never,rarely,sometimes,regularly,daily'],

            // Matrimonial Specific
            'marital_status' => ['sometimes', 'string', 'in:never_married,divorced,widowed,separated'],
            'have_children' => ['sometimes', 'boolean'],
            'children_count' => ['required_if:have_children,true', 'integer', 'min:1', 'max:10'],
            'children_living_status' => ['required_if:have_children,true', 'string', 'in:living_with_me,living_separately,shared_custody'],
            'want_children' => ['sometimes', 'boolean'],

            // About & Bio
            'about_me' => ['sometimes', 'string', 'max:1000'],
            'partner_expectations' => ['sometimes', 'string', 'max:1000'],
            'hobbies_interests' => ['sometimes', 'array'],
            'hobbies_interests.*' => ['string', 'max:100'],

            // Verification & Privacy
            'profile_visibility' => ['sometimes', 'string', 'in:public,members_only,premium_only'],
            'show_contact_info' => ['sometimes', 'boolean'],
            'allow_partner_search' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'height_cm.min' => 'Height must be at least 120 cm.',
            'height_cm.max' => 'Height cannot exceed 250 cm.',
            'weight_kg.min' => 'Weight must be at least 30 kg.',
            'weight_kg.max' => 'Weight cannot exceed 300 kg.',
            'blood_group.in' => 'Please select a valid blood group.',
            'physical_challenge_details.required_if' => 'Please provide details about the physical challenge.',
            'latitude.between' => 'Latitude must be between -90 and 90.',
            'longitude.between' => 'Longitude must be between -180 and 180.',
            'education_level.in' => 'Please select a valid education level.',
            'annual_income.min' => 'Annual income cannot be negative.',
            'income_currency.size' => 'Currency code must be exactly 3 characters.',
            'religion.in' => 'Please select a valid religion.',
            'siblings_count.max' => 'Siblings count cannot exceed 20.',
            'family_type.in' => 'Please select either nuclear or joint family type.',
            'family_values.in' => 'Please select valid family values.',
            'dietary_preferences.in' => 'Please select a valid dietary preference.',
            'smoking_habits.in' => 'Please select a valid smoking habit.',
            'drinking_habits.in' => 'Please select a valid drinking habit.',
            'exercise_habits.in' => 'Please select a valid exercise habit.',
            'marital_status.in' => 'Please select a valid marital status.',
            'children_count.required_if' => 'Please specify the number of children.',
            'children_living_status.required_if' => 'Please specify children living status.',
            'about_me.max' => 'About me section cannot exceed 1000 characters.',
            'partner_expectations.max' => 'Partner expectations cannot exceed 1000 characters.',
            'profile_visibility.in' => 'Please select a valid profile visibility option.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'height_cm' => 'height',
            'weight_kg' => 'weight',
            'body_type' => 'body type',
            'blood_group' => 'blood group',
            'physically_challenged' => 'physically challenged status',
            'physical_challenge_details' => 'physical challenge details',
            'current_city' => 'current city',
            'current_state' => 'current state',
            'current_country' => 'current country',
            'education_level' => 'education level',
            'education_details' => 'education details',
            'college_university' => 'college/university',
            'company_name' => 'company name',
            'job_title' => 'job title',
            'annual_income' => 'annual income',
            'income_currency' => 'income currency',
            'work_location' => 'work location',
            'mother_tongue' => 'mother tongue',
            'other_languages' => 'other languages',
            'father_occupation' => "father's occupation",
            'mother_occupation' => "mother's occupation",
            'siblings_count' => 'number of siblings',
            'family_type' => 'family type',
            'family_values' => 'family values',
            'family_location' => 'family location',
            'family_income' => 'family income',
            'dietary_preferences' => 'dietary preferences',
            'smoking_habits' => 'smoking habits',
            'drinking_habits' => 'drinking habits',
            'exercise_habits' => 'exercise habits',
            'marital_status' => 'marital status',
            'have_children' => 'have children',
            'children_count' => 'number of children',
            'children_living_status' => 'children living status',
            'want_children' => 'want children',
            'about_me' => 'about me',
            'partner_expectations' => 'partner expectations',
            'hobbies_interests' => 'hobbies and interests',
            'profile_visibility' => 'profile visibility',
            'show_contact_info' => 'show contact info',
            'allow_partner_search' => 'allow partner search',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean and prepare string fields
        $stringFields = [
            'current_city', 'current_state', 'current_country', 'education_details',
            'college_university', 'occupation', 'company_name', 'job_title',
            'work_location', 'caste', 'subcaste', 'mother_tongue',
            'father_occupation', 'mother_occupation', 'family_location',
            'about_me', 'partner_expectations'
        ];

        $cleanedData = [];
        foreach ($stringFields as $field) {
            if ($this->filled($field)) {
                $cleanedData[$field] = trim($this->input($field));
            }
        }

        // Handle currency code
        if ($this->filled('income_currency')) {
            $cleanedData['income_currency'] = strtoupper($this->input('income_currency'));
        }

        // Handle arrays
        if ($this->filled('other_languages')) {
            $cleanedData['other_languages'] = array_map('trim', $this->input('other_languages', []));
        }

        if ($this->filled('hobbies_interests')) {
            $cleanedData['hobbies_interests'] = array_map('trim', $this->input('hobbies_interests', []));
        }

        $this->merge($cleanedData);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate location coordinates consistency
            if ($this->filled(['latitude', 'longitude'])) {
                $this->validateLocationCoordinates($validator);
            }

            // Validate family consistency
            $this->validateFamilyConsistency($validator);

            // Validate income currency based on location
            $this->validateIncomeCurrency($validator);

            // Check for inappropriate content
            $this->validateContentAppropriatenesss($validator);
        });
    }

    /**
     * Validate location coordinates
     */
    private function validateLocationCoordinates($validator): void
    {
        $lat = $this->input('latitude');
        $lng = $this->input('longitude');

        // Check if coordinates are reasonable (not 0,0 unless in Gulf of Guinea)
        if ($lat == 0 && $lng == 0) {
            $validator->errors()->add('latitude', 'Please provide valid location coordinates.');
        }
    }

    /**
     * Validate family information consistency
     */
    private function validateFamilyConsistency($validator): void
    {
        // If user has children, validate children-related fields
        if ($this->input('have_children') === true) {
            if (!$this->filled('children_count') || $this->input('children_count') < 1) {
                $validator->errors()->add('children_count', 'Please specify the number of children.');
            }

            if (!$this->filled('children_living_status')) {
                $validator->errors()->add('children_living_status', 'Please specify children living status.');
            }
        }

        // Validate marital status and children consistency
        if ($this->input('marital_status') === 'never_married' && $this->input('have_children') === true) {
            $validator->errors()->add('marital_status', 'Marital status and children information are inconsistent.');
        }
    }

    /**
     * Validate income currency based on user location
     */
    private function validateIncomeCurrency($validator): void
    {
        if ($this->filled('income_currency') && $this->filled('current_country')) {
            $country = $this->input('current_country');
            $currency = $this->input('income_currency');

            $countryCurrencyMap = [
                'Sri Lanka' => 'LKR',
                'India' => 'INR',
                'United States' => 'USD',
                'United Kingdom' => 'GBP',
                'Australia' => 'AUD',
                'Canada' => 'CAD',
            ];

            if (isset($countryCurrencyMap[$country]) && $currency !== $countryCurrencyMap[$country] && $currency !== 'USD') {
                $expectedCurrency = $countryCurrencyMap[$country];
                $validator->errors()->add('income_currency', "For {$country}, expected currency is {$expectedCurrency} or USD.");
            }
        }
    }

    /**
     * Check for inappropriate content in text fields
     */
    private function validateContentAppropriatenesss($validator): void
    {
        $textFields = ['about_me', 'partner_expectations', 'education_details'];
        $inappropriateWords = ['spam', 'scam', 'money', 'whatsapp', 'telegram', 'cash'];

        foreach ($textFields as $field) {
            if ($this->filled($field)) {
                $text = strtolower($this->input($field));
                
                foreach ($inappropriateWords as $word) {
                    if (strpos($text, $word) !== false) {
                        $validator->errors()->add($field, 'Please remove inappropriate content from this field.');
                        break;
                    }
                }
            }
        }
    }
} 