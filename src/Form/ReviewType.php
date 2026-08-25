<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Review;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Review>
 */
final class ReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('companyName', TextType::class, [
                'label' => 'Company name',
                'attr' => [
                    'placeholder' => 'e.g. Acme Ltd.',
                    'autocomplete' => 'organization',
                ],
            ])
            ->add('rating', ChoiceType::class, [
                'label' => 'Rating',
                'choices' => [
                    '★★★★★ — Excellent' => 5,
                    '★★★★☆ — Very good' => 4,
                    '★★★☆☆ — Good' => 3,
                    '★★☆☆☆ — Mixed' => 2,
                    '★☆☆☆☆ — Poor' => 1,
                ],
            ])
            ->add('reviewText', TextareaType::class, [
                'label' => 'Your review',
                'attr' => [
                    'rows' => 7,
                    'placeholder' => 'What should other customers know?',
                ],
            ])
            ->add('authorEmail', EmailType::class, [
                'label' => 'Email address',
                'help' => 'Used for validation/contact only. It is never shown publicly.',
                'attr' => [
                    'placeholder' => 'you@example.com',
                    'autocomplete' => 'email',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Submit review',
                'attr' => ['class' => 'button'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
        ]);
    }
}
