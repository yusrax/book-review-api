<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints as Assert;

class UserRegisterType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return '';
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Email is required'),
                    new Assert\Email(message: 'Invalid email address'),
                ]
            ])
            ->add('password', PasswordType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Password is required'),
                    new Assert\Length([
                        'min' => 8,
                        'minMessage' => 'Password must be at least 8 characters long',
                    ]),
                ]
            ])
            ->add('name', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Name is required'),
                    new Assert\Length([
                        'min' => 2,
                        'minMessage' => 'Name must be at least 2 characters long',
                    ]),
                ]
            ])
            ->add('profilePicture', FileType::class, [
                'mapped' => false, // Important: This field isn't mapped directly to the entity
                'required' => false,
                'empty_data' => null,
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png'],
                        'mimeTypesMessage' => 'Please upload a valid JPEG or PNG image.',
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false, // Disable CSRF for API
            'allow_extra_fields' => true,
            'data_class' => \App\Entity\User::class, // Set the data class to User entity
        ]);
    }
}
