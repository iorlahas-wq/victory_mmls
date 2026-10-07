<?php

require_once __DIR__ . '/app/config/app.php';

$pageTitle = "Home";
$showNavbar = true;

require_once __DIR__ . '/app/includes/header.php';

?>

<main>

    <!-- ======================================================
         HERO SECTION
    ======================================================= -->

    <section class="hero-section">

        <div class="container hero-content">

            <div class="hero-text">

                <span class="hero-label">
                    WEB-BASED MULTIMEDIA LEARNING SYSTEM
                </span>

                <h1>
                    Learn. Watch.<br>
                    Practise.
                </h1>

                <p>
                    Learn practical vocational skills through
                    structured lessons, instructional images,
                    videos and online assessments.
                </p>

                <div class="hero-actions">

                    <a
                        href="<?= e(url('login.php')) ?>"
                        class="btn btn-primary"
                    >
                        Start Learning
                    </a>

                    <a
                        href="#learning"
                        class="btn btn-outline"
                    >
                        Explore Learning
                    </a>

                </div>

            </div>

        </div>

    </section>


    <!-- ======================================================
         INTRODUCTION
    ======================================================= -->

    <section class="intro-section">

        <div class="container intro-grid">

            <div class="intro-heading">

                <span class="section-label">
                    VOCATIONAL LEARNING
                </span>

                <h2>
                    Practical skills made easier to learn
                </h2>

            </div>


            <div class="intro-text">

                <p>
                    The Ebenezer's Kitchen Multimedia Learning
                    System provides learners with organised
                    vocational lessons that combine text,
                    images and instructional videos.
                </p>

                <p>
                    Learners can study lessons at their own pace,
                    practise the skills demonstrated and complete
                    short assessments to check their understanding.
                </p>

            </div>

        </div>

    </section>


    <!-- ======================================================
         LEARNING FEATURES
    ======================================================= -->

    <section
        class="features-section"
        id="learning"
    >

        <div class="container">

            <div class="section-heading">

                <span class="section-label">
                    LEARNING FEATURES
                </span>

                <h2>
                    Everything learners need to practise
                </h2>

                <p>
                    The system combines simple learning materials
                    with practical multimedia content and assessment.
                </p>

            </div>


            <div class="features-grid">


                <!-- FEATURE 1 -->

                <article class="feature-card">

                    <div class="feature-number">
                        01
                    </div>

                    <h3>
                        Structured Lessons
                    </h3>

                    <p>
                        Learn vocational skills through organised
                        lessons presented in a clear sequence.
                    </p>

                </article>


                <!-- FEATURE 2 -->

                <article class="feature-card">

                    <div class="feature-number">
                        02
                    </div>

                    <h3>
                        Instructional Videos
                    </h3>

                    <p>
                        Watch practical demonstrations that help
                        learners understand how tasks are performed.
                    </p>

                </article>


                <!-- FEATURE 3 -->

                <article class="feature-card">

                    <div class="feature-number">
                        03
                    </div>

                    <h3>
                        Learning Images
                    </h3>

                    <p>
                        View useful images that support explanations
                        and practical demonstrations.
                    </p>

                </article>


                <!-- FEATURE 4 -->

                <article class="feature-card">

                    <div class="feature-number">
                        04
                    </div>

                    <h3>
                        Online Assessment
                    </h3>

                    <p>
                        Complete short quizzes and receive results
                        after submitting an assessment.
                    </p>

                </article>


                <!-- FEATURE 5 -->

                <article class="feature-card">

                    <div class="feature-number">
                        05
                    </div>

                    <h3>
                        Progress Tracking
                    </h3>

                    <p>
                        Keep track of completed lessons and continue
                        learning from where you stopped.
                    </p>

                </article>


                <!-- FEATURE 6 -->

                <article class="feature-card">

                    <div class="feature-number">
                        06
                    </div>

                    <h3>
                        Accessible Learning
                    </h3>

                    <p>
                        Access learning materials through a web browser
                        using a computer or other suitable device.
                    </p>

                </article>

            </div>

        </div>

    </section>


    <!-- ======================================================
         ABOUT SECTION
    ======================================================= -->

    <section
        class="about-section"
        id="about"
    >

        <div class="container about-grid">

            <div class="about-image">

                <img
                    src="<?= e(url('assets/images/mmls-logo.jpg')) ?>"
                    alt="Ebenezer's Kitchen Vocational Skills Training Centre"
                >

            </div>


            <div class="about-content">

                <span class="section-label">
                    ABOUT THE SYSTEM
                </span>

                <h2>
                    Learning practical skills through multimedia
                </h2>

                <p>
                    This learning system was developed to support
                    vocational skills acquisition at
                    Ebenezer's Kitchen Vocational Skills Training Centre.
                </p>

                <p>
                    It provides a simple online environment where
                    learners can access lessons, study instructional
                    materials, watch practical demonstrations and
                    complete assessments.
                </p>

                <a
                    href="<?= e(url('login.php')) ?>"
                    class="btn btn-primary"
                >
                    Access Learning System
                </a>

            </div>

        </div>

    </section>


    <!-- ======================================================
         CALL TO ACTION
    ======================================================= -->

    <section class="cta-section">

        <div class="container cta-inner">

            <div>

                <span class="section-label">
                    BEGIN YOUR LEARNING
                </span>

                <h2>
                    Ready to develop a practical skill?
                </h2>

                <p>
                    Log in to access your learning materials,
                    lessons and assessments.
                </p>

            </div>


            <a
                href="<?= e(url('login.php')) ?>"
                class="btn btn-light"
            >
                Login to Learn
            </a>

        </div>

    </section>

</main>


<?php

require_once __DIR__ . '/app/includes/footer.php';

?>